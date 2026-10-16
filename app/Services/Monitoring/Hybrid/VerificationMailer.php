<?php

namespace App\Services\Monitoring\Hybrid;

use App\Support\Senders\SenderPool;
use App\Models\Monitoring\MpAsk;
use App\Models\Monitoring\MpProduct;
use App\Services\Api\OpenAIClassifierClient;
use App\Services\Generate\Batch;
use App\Services\Generate\CampaignBodyGenerator;
use App\Services\Generate\CampaignEmailBuilder;
use App\Services\Generate\CampaignPersister;
use App\Services\Generate\CampaignSenderAssigner;
use App\Services\Generate\CampaignTokenGenerator;
use App\Services\Generate\SenderDailyCapacity;
use App\Services\Monitoring\MonitoringGovernor;
use App\Support\Monitoring\ItemScope;
use App\Support\Monitoring\MonitoringSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Отправка проверочных КП (Ф3 «Гибрид»): mp_asks(planned) → адресные письма поставщикам через
 * общий почтовый движок. Решения владельца 2026-10-15: техническая заявка программы в reports
 * (source='monitoring'), до N писем в сутки (агрессивность), приоритет у заявок клиентов —
 * поэтому запуск ПОСЛЕ plan-day и с учётом уже занятых капов.
 *
 *  - получатель: поставщик сайта (mp_sites.supplier_id) или пула, с остатком дневного капа,
 *    не заблокирован, не на паузе отписки;
 *  - ящик: активный, не персональный, остаток дневного лимита (SenderDailyCapacity — учитывает
 *    уже поставленное на сегодня), не писал этому получателю 7 дней; шаблон/тон/угол — ящика;
 *  - письмо: позиций — как в обычной рассылке (itemsPerLetter); у каждой запроса своя позиция тех. заявки (повторно спрашивать ту же
 *    позицию тот же поставщик persister не даст), цена сайта — в названии позиции (видна в любом
 *    формате шаблона и в AI-разборе ответа), страница — в описании.
 */
class VerificationMailer
{
    private const CONN = 'reports';

    private const HELD = '2037-01-01';

    /**
     * Три вида писем мониторинга, по очереди (письма разных видов не смешиваются):
     *  verify     — «подтвердите цену с вашего сайта» (бюджет «проверочных писем в сутки»);
     *  on_request — «видели товар у вас на сайте, цены нет — пришлите КП» (адресно, со ссылкой);
     *  pool       — обычный запрос КП профильному пулу.
     * Решения владельца 2026-10-15: письма малопозиционные — размер как у обычной рассылки
     * (services.email_planner.dayplan_max_per_email; анализ 05.09–05.10: в письме на 1 позицию
     * цен на позицию в 4,7 раза больше, чем в письме на 4, у тех же получателей); сколько писем
     * получит поставщик — решают только общие лимиты: остаток дневного капа получателя (после
     * заявок клиентов), ящики с остатком ёмкости, не писавшие ему 7 дней. Отдельного кулдауна нет.
     *
     * @return array{letters: int, items: int, skipped_suppliers: int, skipped?: array<string,int>, by_kind?: array<string,int>}
     */
    public function send(bool $dryRun = false): array
    {
        // Только активные программы в Гибриде: пауза или возврат в lite останавливают и уже запланированные письма.
        $programs = DB::table('monitoring_programs')->where('status', 'active')->where('price_mode', 'hybrid')->pluck('id');
        $all = MpAsk::query()->where('status', MpAsk::STATUS_PLANNED)->where('expires_at', '>', now())
            ->whereIn('program_id', $programs)->orderBy('created_at')->get();
        if ($all->isEmpty()) {
            return ['letters' => 0, 'items' => 0, 'skipped_suppliers' => 0];
        }
        $suppliers = $this->eligibleSuppliers($all->pluck('supplier_id')->unique()->all());
        $recipientLeft = $this->recipientCaps(array_map(fn ($s) => mb_strtolower(trim($s->email)), $suppliers));
        $senders = $this->senders();
        $pairs = $this->recentPairs();
        $perLetter = self::itemsPerLetter();

        $tools = $this->tools();
        $out = ['letters' => 0, 'items' => 0, 'skipped' => [], 'by_kind' => []];
        $skip = function (string $reason) use (&$out) {
            $out['skipped'][$reason] = ($out['skipped'][$reason] ?? 0) + 1;
        };
        $kinds = [
            'verify' => fn (MpAsk $a) => $a->kind === 'verify',
            'on_request' => fn (MpAsk $a) => $a->kind === 'rfq' && $a->reason === 'on_request',
            'pool' => fn (MpAsk $a) => $a->kind === 'rfq' && $a->reason === 'pool',
        ];
        foreach ($kinds as $kind => $filter) {
            $budget = $kind === 'verify'
                ? MonitoringSettings::get('verify_letters_per_day') - $this->lettersToday()
                : PHP_INT_MAX;
            $sent = 0;
            foreach ($all->filter($filter)->groupBy('supplier_id') as $supplierId => $supplierAsks) {
                $sup = $suppliers[$supplierId] ?? null;
                if (!$sup) {
                    $skip('не годен к рассылке');
                    continue;
                }
                $email = mb_strtolower(trim($sup->email));
                if ($kind === 'verify') {
                    // Цена листинга с момента выборки снята (заглушка, аналог, «по запросу») или изменилась —
                    // спрашивать про неё нельзя: такие запросы отменяем.
                    $supplierAsks = $supplierAsks->filter(function (MpAsk $a) {
                        $l = $a->listing;
                        $ok = $l && $l->status === \App\Models\Monitoring\MpListing::STATUS_ACTIVE && $l->price !== null
                            && abs((float) $l->price - (float) $a->site_price) < 0.01;
                        if (!$ok) {
                            $a->forceFill(['status' => MpAsk::STATUS_CANCELLED])->save();
                        }

                        return $ok;
                    });
                }
                // Письма поставщику, пока позволяют общие лимиты (кап получателя, свободный ящик).
                foreach ($supplierAsks->values()->chunk($perLetter) as $letterAsks) {
                    if ($sent >= $budget) {
                        break 2;
                    }
                    if (($recipientLeft[$email] ?? 0) <= 0) {
                        $skip('кап получателя на сегодня');
                        break;
                    }
                    $senderId = $this->pickSender($senders, $pairs, $email);
                    if ($senderId === null) {
                        $skip('нет свободного ящика');
                        break;
                    }
                    $letterAsks = $letterAsks->values();
                    try {
                        if ($dryRun || $this->sendLetter($letterAsks, $sup, $senderId, $tools, $kind)) {
                            $sent++;
                            $out['letters']++;
                            $out['items'] += $letterAsks->count();
                            $senders[$senderId]--;
                            $pairs[$senderId . '|' . $email] = true;
                            $recipientLeft[$email]--;
                            if (!$dryRun) {
                                app(MonitoringGovernor::class)->record('email', 1, 0.0);
                            }
                        } else {
                            $skip('пропущено при записи');
                            break;
                        }
                    } catch (\Throwable $e) {
                        Log::error('VerificationMailer: письмо не собрано', ['supplier_id' => $supplierId, 'kind' => $kind, 'error' => $e->getMessage()]);
                        $skip('ошибка');
                        break;
                    }
                }
            }
            $out['by_kind'][$kind] = $sent;
        }

        return ['letters' => $out['letters'], 'items' => $out['items'], 'skipped_suppliers' => array_sum($out['skipped']),
            'skipped' => $out['skipped'], 'by_kind' => $out['by_kind']];
    }

    /** Позиций в письме мониторинга — как в обычной рассылке (малопозиционные письма). */
    public static function itemsPerLetter(): int
    {
        return max(1, (int) config('services.email_planner.dayplan_max_per_email', 4));
    }

    /** @param \Illuminate\Support\Collection<int, MpAsk> $asks */
    private function sendLetter($asks, object $sup, int $senderId, array $tools, string $kind = 'verify'): bool
    {
        $programId = (int) ($asks->first()->program_id ?? 0);
        $request = $this->techRequest($programId);
        $products = MpProduct::query()->with('identifiers')->whereIn('id', $asks->pluck('product_id'))->get()->keyBy('id');

        // Позиции тех. заявки — по одной на запрос.
        $itemRows = [];
        $pos = (int) DB::connection(self::CONN)->table('request_items')->where('request_id', $request->id)->max('position_number');
        foreach ($asks as $ask) {
            $p = $products[$ask->product_id] ?? null;
            if (!$p) {
                continue;
            }
            $ask->loadMissing('listing');
            // Название и артикул — позиции клиента (не общего товара: у него могут быть кросс-номера
            // других вариантов, M02502); артикул — код, найденный на странице поставщика, если он есть.
            [$baseName, $article] = $this->naming($ask, $p);
            if ($kind === 'verify') {
                $priceText = number_format((float) $ask->site_price, fmod((float) $ask->site_price, 1.0) > 0 ? 2 : 0, ',', ' ') . ' ₽';
                $vat = $ask->site_vat_included === null ? '' : ($ask->site_vat_included ? ' с НДС' : ' без НДС');
                $avail = ['in_stock' => 'в наличии', 'on_order' => 'под заказ', 'out_of_stock' => 'нет в наличии'][$ask->site_availability] ?? null;
                $name = $baseName . " — на вашем сайте {$priceText}{$vat}";
                $description = 'Цена на сайте: ' . $priceText . $vat . ($avail ? ", {$avail}" : '') . '. Страница: ' . ($ask->listing?->url ?? '');
            } elseif ($kind === 'on_request') {
                $name = $baseName;
                $description = 'Товар на вашем сайте (цена не указана): ' . ($ask->listing?->url ?? '');
            } else {
                $name = $baseName;
                $description = null;
            }
            $row = [
                'request_id' => $request->id,
                'position_number' => ++$pos,
                'name' => mb_substr($name, 0, 1000),
                'brand' => $p->brand ? mb_substr($p->brand, 0, 255) : null,
                'article' => $article ? mb_substr($article, 0, 255) : null,
                'quantity' => 1,
                'unit' => 'шт',
                'description' => $description !== null ? mb_substr($description, 0, 2000) : null,
                'status' => 'pending',
                'product_type_id' => $p->product_type_id ?: null,
                'domain_id' => $p->domain_id ?: null,
                'created_at' => now(),
            ];
            $id = (int) DB::connection(self::CONN)->table('request_items')->insertGetId($row);
            $itemRows[$ask->id] = ['id' => $id] + $row + ['request_number' => $request->request_number, 'is_customer_request' => 0,
                'category' => null, 'subject_name' => $baseName];
        }
        if ($itemRows === []) {
            return false;
        }

        $batch = new Batch();
        // verify — «подтвердите цену с сайта»; rfq_site — «видели у вас на сайте, пришлите КП»;
        // пул — обычный запрос КП (purpose null, стандартный промпт).
        $batch->purpose = ['verify' => 'verify', 'on_request' => 'rfq_site'][$kind] ?? null;
        $batch->items = array_values($itemRows);
        $batch->itemsCount = count($itemRows);
        $batch->useNewRouting = true;
        $batch->requestIds = [(int) $request->id];
        $batch->requestNumbers = [$request->request_number];
        $batch->productTypeIds = array_values(array_unique(array_filter(array_column($batch->items, 'product_type_id'))));
        $batch->domainIds = array_values(array_unique(array_filter(array_column($batch->items, 'domain_id'))));
        $batch->category = 'Мониторинг';
        $batch->targetCategories = ['NEW_ROUTING'];
        $batch->sender = $tools['assigner']->fullSender($senderId);
        if (empty($batch->sender['email'])) {
            DB::connection(self::CONN)->table('request_items')->whereIn('id', array_column($itemRows, 'id'))->delete();

            return false;
        }
        $supRow = ['id' => (int) $sup->id, 'name' => $sup->name, 'email' => $sup->email, 'contact_person' => $sup->contact_person, 'categories' => $sup->categories];
        $batch->suppliers = [$supRow];
        $batch->supplierIds = [(int) $sup->id];

        $tools['token']->generate($batch);
        $tools['body']->generate($batch);
        $email = $tools['builder']->build($batch, $supRow);
        $email['wave'] = 1;
        $res = $tools['persister']->persist($batch, [$email]);
        if ($res['queue_ids'] === []) {
            // Persister молча пропустил (ящик писал этому адресу / поставщика уже спрашивали) —
            // позиции тех. заявки убираем, запросы остаются в плане.
            DB::connection(self::CONN)->table('request_items')->whereIn('id', array_column($itemRows, 'id'))->delete();

            return false;
        }

        foreach ($asks as $ask) {
            if (!isset($itemRows[$ask->id])) {
                continue;
            }
            $ask->forceFill([
                'status' => MpAsk::STATUS_QUEUED, 'request_item_id' => $itemRows[$ask->id]['id'],
                'batch_id' => (int) $res['batch_id'], 'email_queue_id' => (int) $res['queue_ids'][0], 'queued_at' => now(),
            ])->save();
        }
        DB::connection(self::CONN)->table('requests')->where('id', $request->id)
            ->update(['total_items' => DB::raw('total_items + ' . count($itemRows)), 'updated_at' => now()]);

        return true;
    }

    /**
     * Название и артикул для письма: позиция программы, которой видна страница запроса (ItemScope);
     * артикул — её код, найденный на странице, иначе её артикул, иначе первый OEM товара.
     *
     * @return array{0: string, 1: ?string}
     */
    private function naming(MpAsk $ask, MpProduct $p): array
    {
        $productCodes = $p->identifiers->whereIn('kind', ['oem', 'mpn'])->pluck('value_norm')->map(fn ($v) => (string) $v)->all();
        $listingCodes = $ask->listing ? ItemScope::listingCodes($ask->listing->matched_codes, $ask->listing->match_method, $ask->listing->match_evidence) : [];
        $item = \App\Models\Monitoring\ProgramItem::query()->where('program_id', $ask->program_id)->where('product_id', $p->id)
            ->where('active', true)->get()
            ->first(fn ($i) => !$ask->listing || ItemScope::allows(ItemScope::itemCodes($i), $productCodes, $listingCodes));
        $name = trim((string) ($item?->raw_name ?: $p->name));

        $own = $item ? ItemScope::itemCodes($item) : $productCodes;
        $code = array_values(array_intersect($listingCodes, $own ?: $productCodes))[0] ?? null;
        if ($code !== null) {
            $raw = $p->identifiers->first(fn ($i) => (string) $i->value_norm === $code);

            return [$name, $raw?->value ?? $code];
        }
        if ($item?->raw_article) {
            return [$name, $item->raw_article];
        }
        $first = $p->identifiers->first(fn ($i) => in_array($i->kind, ['oem', 'mpn'], true) && ($own === [] || in_array((string) $i->value_norm, $own, true)));

        return [$name, $first?->value];
    }

    /** Техническая заявка программы в reports (source=monitoring, системный пользователь). */
    public function techRequest(int $programId): object
    {
        $program = DB::table('monitoring_programs')->where('id', $programId)->first();
        $number = 'MON-' . ($program->external_id ?? $programId);
        $row = DB::connection(self::CONN)->table('requests')->where('request_number', $number)->first();
        if ($row) {
            return $row;
        }
        DB::connection(self::CONN)->table('requests')->insert([
            'user_id' => (int) config('services.n8n.system_user_id', 1),
            'request_number' => $number,
            'title' => mb_substr('Мониторинг цен: ' . ($program->name ?? "программа {$programId}") . ' — проверка цен и запросы КП', 0, 500),
            'status' => 'active',
            'source' => 'monitoring',
            'is_customer_request' => 0,
            'total_items' => 0,
            'items_with_offers' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::connection(self::CONN)->table('requests')->where('request_number', $number)->first();
    }

    private function lettersToday(): int
    {
        return (int) MpAsk::query()->where('kind', 'verify')->where('queued_at', '>=', now()->startOfDay())->distinct()->count('batch_id');
    }

    /** @return array<int, object> supplier_id => строка поставщика (только годные к рассылке) */
    private function eligibleSuppliers(array $ids): array
    {
        $rows = DB::connection(self::CONN)->table('suppliers as s')->whereIn('s.id', $ids)
            ->where('s.is_active', 1)->where('s.notify_email', 1)->whereNotNull('s.email')->where('s.email', '!=', '')
            ->where(fn ($q) => $q->whereNull('s.unsubscribe_until')->orWhere('s.unsubscribe_until', '<', now()))
            ->whereRaw("NOT EXISTS (SELECT 1 FROM blocked_domains bd WHERE bd.domain = SUBSTRING_INDEX(LOWER(s.email), '@', -1))")
            ->whereRaw('NOT EXISTS (SELECT 1 FROM recipient_mailboxes rm WHERE rm.is_blocked = 1 AND rm.email = LOWER(TRIM(s.email)))')
            ->get(['s.id', 's.name', 's.email', 's.contact_person', 's.categories']);

        return $rows->keyBy('id')->all();
    }

    /** @return array<string,int> email => остаток дневного капа (как PlanDayCampaign::recipientCaps) */
    private function recipientCaps(array $emails): array
    {
        $emails = array_values(array_unique(array_filter($emails)));
        if ($emails === []) {
            return [];
        }
        $baseCap = (int) config('services.email_dispatch.recipient_daily_cap', 10);
        $today = Carbon::now('Europe/Moscow')->toDateString();
        $pending = DB::connection(self::CONN)->table('email_queue')->whereIn(DB::raw('LOWER(to_email)'), $emails)
            ->where('status', 'pending')->where('scheduled_at', '<', self::HELD)
            ->selectRaw('LOWER(to_email) r, COUNT(*) c')->groupBy(DB::raw('LOWER(to_email)'))->pluck('c', 'r');
        $meta = DB::connection(self::CONN)->table('recipient_mailboxes')->whereIn('email', $emails)
            ->get(['email', 'daily_cap', 'daily_sent_count', 'daily_sent_date', 'is_blocked'])->keyBy('email');
        $out = [];
        foreach ($emails as $e) {
            $r = $meta[$e] ?? null;
            if ($r && (int) $r->is_blocked === 1) {
                $out[$e] = 0;
                continue;
            }
            $cap = ($r && $r->daily_cap !== null) ? (int) $r->daily_cap : $baseCap;
            $sent = ($r && (string) $r->daily_sent_date === $today) ? (int) $r->daily_sent_count : 0;
            $out[$e] = max(0, $cap - $sent - (int) ($pending[$e] ?? 0));
        }

        return $out;
    }

    /** @return array<int,int> sender_id => остаток на сегодня (не персональные, рабочие) */
    private function senders(): array
    {
        $ids = SenderPool::onlyMain(DB::connection(self::CONN)->table('senders'))->where('is_active', 1)
            ->where(fn ($q) => $q->whereNull('sending_disabled')->orWhere('sending_disabled', 0))
            ->where(fn ($q) => $q->whereNull('blocked_until')->orWhere('blocked_until', '<=', now()))
            ->where('daily_limit', '>', 0)
            ->where(fn ($q) => $q->whereNull('is_personal')->orWhere('is_personal', 0))
            ->pluck('id')->map(fn ($v) => (int) $v)->all();

        return array_filter((new SenderDailyCapacity())->remainingMap($ids), fn ($left) => $left > 0);
    }

    /** @return array<string,true> "sender_id|email" за 7 дней (ротация ящик × получатель) */
    private function recentPairs(): array
    {
        $days = (int) config('services.email_generate.sender_recipient_days', 7);
        $out = [];
        foreach (DB::connection(self::CONN)->table('email_queue')->where('created_at', '>=', now()->subDays($days))
            ->whereIn('status', ['pending', 'sending', 'sent', 'opened', 'replied', 'reply_processed', 'in_conversation', 'completed'])
            ->selectRaw('DISTINCT sender_id, LOWER(to_email) e')->get() as $r) {
            $out[(int) $r->sender_id . '|' . $r->e] = true;
        }

        return $out;
    }

    /** Ящик с наибольшим остатком, не писавший получателю 7 дней. */
    private function pickSender(array $senders, array $pairs, string $email): ?int
    {
        $best = null;
        foreach ($senders as $id => $left) {
            if ($left <= 0 || isset($pairs[$id . '|' . $email])) {
                continue;
            }
            if ($best === null || $left > $senders[$best] || ($left === $senders[$best] && random_int(0, 1) === 1)) {
                $best = $id;
            }
        }

        return $best;
    }

    /** @return array{assigner: CampaignSenderAssigner, token: CampaignTokenGenerator, body: CampaignBodyGenerator, builder: CampaignEmailBuilder, persister: CampaignPersister} */
    private function tools(): array
    {
        $cfg = config('services.email_generate');
        $client = OpenAIClassifierClient::fromConfig();

        return [
            'assigner' => new CampaignSenderAssigner(),
            'token' => new CampaignTokenGenerator($client, (string) ($cfg['token_model'] ?? 'gpt-4o-mini'), (bool) ($cfg['token_use_ai'] ?? true)),
            'body' => new CampaignBodyGenerator($client, (string) ($cfg['body_model'] ?? 'gpt-4o'), (float) ($cfg['body_temperature'] ?? 0.7), (int) ($cfg['max_tokens'] ?? 1500)),
            'builder' => new CampaignEmailBuilder(),
            'persister' => new CampaignPersister(),
        ];
    }
}
