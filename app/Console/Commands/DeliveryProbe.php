<?php

namespace App\Console\Commands;

use App\Models\Reports\Sender;
use App\Services\Senders\RelayHttpMailer;
use App\Support\Mail\TestMode;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\IMAP;

/**
 * Проверка доставки по ящикам (20.11.2026, после аварии Beget): с каждого ящика — одно служебное письмо на контрольный
 * адрес (services.delivery_probe, Яндекс 360), затем чтение контрольного ящика по IMAP («Входящие» и «Спам») и, для
 * недошедших, отказов во входящих ящика-отправителя. Проверка подключаемости (SenderConnectivityChecker) этого не видит:
 * Beget принимает письмо и отключённого для отправки ящика, отказ приходит потом («retry timeout exceeded»).
 * --apply: ящик с отказом при отправке или с NDR снимается с рассылки (is_active=0, block_reason «delivery probe: …»).
 */
class DeliveryProbe extends Command
{
    protected $signature = 'emails:delivery-probe
        {--senders= : id ящиков через запятую (по умолчанию все активные)}
        {--wait=600 : сколько секунд ждать писем в контрольном ящике}
        {--pause=5 : пауза между ящиками, секунд}
        {--apply : снять с рассылки ящики с отказом}
        {--collect= : не слать, а собрать итог прошлого прогона по его файлу (storage/app/delivery-probe/….json) или коду прогона Pччммсс}';

    protected $description = 'Проверка доставки: письмо с каждого ящика на контрольный адрес и поиск его во «Входящих»/«Спаме»';

    public function handle(): int
    {
        $cfg = (array) config('services.delivery_probe', []);
        $to = mb_strtolower(trim((string) ($cfg['imap_user'] ?? '')));
        if ($to === '' || empty($cfg['imap_password'])) {
            $this->error('Не задан контрольный ящик: DELIVERY_PROBE_IMAP_USER / DELIVERY_PROBE_IMAP_PASSWORD');

            return self::FAILURE;
        }
        if (!TestMode::allows($to)) {
            $this->error("Тестовый режим: {$to} нет в EMAILS_TEST_RECIPIENTS");

            return self::FAILURE;
        }

        $ids = array_filter(array_map('intval', explode(',', (string) $this->option('senders'))));
        $senders = Sender::query()->when($ids, fn ($q) => $q->whereIn('id', $ids), fn ($q) => $q->where('is_active', 1))->orderBy('id')->get();
        $relay = new RelayHttpMailer();
        $collect = trim((string) $this->option('collect'));
        if ($this->option('collect') !== null && $collect === '') {
            // Пустой --collect запускал новый полный прогон (20.11.2026: лишние 68 писем).
            $this->error('--collect: укажите файл журнала или код прогона (Pччммсс)');

            return self::FAILURE;
        }
        $run = now()->format('His');
        $results = [];
        $dir = storage_path('app/delivery-probe');
        @mkdir($dir, 0775, true);

        if ($collect !== '') {
            // Сбор итога прошлого прогона: из его файла, а без файла — по коду прогона для выбранных/активных ящиков.
            if (is_file($collect) || is_file("{$dir}/{$collect}")) {
                foreach (json_decode((string) file_get_contents(is_file($collect) ? $collect : "{$dir}/{$collect}"), true) ?: [] as $r) {
                    $results[$r['id']] = ['sent_at' => Carbon::parse($r['sent_at'])] + $r;
                }
            } elseif (preg_match('/^P?(\d{6})$/', $collect, $m)) {
                foreach ($senders as $s) {
                    $results[$s->id] = ['id' => $s->id, 'email' => $s->email, 'code' => "P{$m[1]}-{$s->id}", 'sent_at' => now()->startOfDay(), 'state' => 'sent', 'detail' => ''];
                }
            } else {
                $this->error("--collect: нет файла и не код прогона: {$collect}");

                return self::FAILURE;
            }
        }

        $this->info($collect !== '' ? 'Сбор итога прогона ' . $collect : "Отправка с {$senders->count()} ящиков на {$to}");
        foreach ($collect !== '' ? [] : $senders as $s) {
            $code = 'P' . $run . '-' . $s->id;
            $r = ['id' => $s->id, 'email' => $s->email, 'code' => $code, 'sent_at' => now(), 'state' => 'sent', 'detail' => ''];
            if (!$relay->handlesSender((int) $s->id)) {
                $r['state'] = 'skip';
                $r['detail'] = 'не через релей';
                $results[$s->id] = $r;
                continue;
            }
            $domain = substr(strrchr((string) $s->email, '@'), 1);
            $text = "Добрый день! Служебная проверка доставки почты {$code}. Отвечать не нужно.";
            try {
                $relay->send([
                    'smtp_server' => (string) $s->smtp_server, 'smtp_port' => (int) ($s->smtp_port ?: 465),
                    'smtp_user' => (string) ($s->smtp_user ?: $s->email), 'smtp_password' => (string) $s->smtp_password,
                    'smtp_encryption' => (string) ($s->smtp_encryption ?: 'ssl'),
                    'from_email' => (string) $s->email, 'from_name' => trim((string) ($s->sender_name ?? '')) ?: null,
                    'to_email' => $to, 'subject' => "Проверка доставки {$code}",
                    'body_html' => '<p>' . htmlspecialchars($text) . '</p>', 'body_text' => $text,
                    'message_id' => '<' . bin2hex(random_bytes(12)) . '@' . $domain . '>', 'reply_to' => (string) $s->email,
                    'attachments' => [], 'verify_cert' => $s->smtp_server === 'smtp.beget.com',
                ], (int) $s->id, 0);
            } catch (\Throwable $e) {
                $r['state'] = 'send_error';
                $r['detail'] = mb_substr(preg_replace('/\s+/', ' ', $e->getMessage()), 0, 160);
            }
            $results[$s->id] = $r;
            $this->line(sprintf('  #%-4d %-34s %s', $s->id, $s->email, $r['state'] === 'sent' ? 'отправлено' : "{$r['state']}: {$r['detail']}"));
            // Beget отвечает «435 Unable to authenticate at present» на частые входы через один релей.
            usleep(max(0, (int) $this->option('pause')) * 1_000_000);
        }

        if ($collect === '') {
            // Журнал отправки — сразу: сбой при ожидании не теряет коды и ошибки отправки (сбор потом — --collect=файл).
            file_put_contents("{$dir}/P{$run}.json", json_encode(array_values($results), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $this->line("Журнал отправки: {$dir}/P{$run}.json");
        }

        $pending = array_filter($results, fn ($r) => $r['state'] === 'sent');
        $deadline = $collect !== '' ? time() + 1 : time() + max(30, (int) $this->option('wait'));
        $this->info('Жду писем в контрольном ящике до ' . date('H:i:s', $deadline) . ' UTC…');
        $first = true;
        while ($pending !== [] && time() < $deadline) {
            if (!($first && $collect !== '')) {
                sleep(30);
            }
            $first = false;
            foreach ($this->probeFolders($cfg) as $folder => $subjects) {
                foreach ($pending as $id => $r) {
                    if (isset($subjects[$r['code']])) {
                        $results[$id]['state'] = $folder;
                        unset($pending[$id]);
                    }
                }
            }
            $this->line('  найдено ' . (count(array_filter($results, fn ($r) => in_array($r['state'], ['inbox', 'spam'], true)))) . ', ждём ' . count($pending));
        }

        foreach ($pending as $id => $r) {
            $results[$id]['state'] = 'missing';
            $ndr = $this->bounce($senders->firstWhere('id', $id), $to, $r['sent_at']);
            if ($ndr !== null) {
                $results[$id]['state'] = 'bounced';
                $results[$id]['detail'] = $ndr;
            }
        }

        $labels = ['inbox' => 'Входящие', 'spam' => 'СПАМ', 'bounced' => 'ОТКАЗ', 'missing' => 'не дошло', 'send_error' => 'ошибка отправки', 'skip' => 'пропущен'];
        $this->table(['id', 'ящик', 'итог', 'подробности'], array_map(fn ($r) => [$r['id'], $r['email'], $labels[$r['state']] ?? $r['state'], $r['detail']], $results));
        $counts = array_count_values(array_column($results, 'state'));
        $this->info('Итого: ' . implode(', ', array_map(fn ($k, $v) => ($labels[$k] ?? $k) . " {$v}", array_keys($counts), $counts)));

        if ($this->option('apply')) {
            $off = 0;
            foreach ($results as $r) {
                if (in_array($r['state'], ['bounced', 'send_error'], true)) {
                    DB::connection('reports')->table('senders')->where('id', $r['id'])->update([
                        'is_active' => 0, 'last_block_at' => now(), 'updated_at' => now(),
                        'block_reason' => mb_substr('delivery probe ' . now()->format('d.m.Y') . ': ' . $r['detail'], 0, 255),
                    ]);
                    $off++;
                }
            }
            $this->warn("Снято с рассылки: {$off}");
        }
        $path = storage_path('app/delivery-probe/' . now()->format('Ymd-His') . '.json');
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, json_encode(array_values($results), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->line("Подробно: {$path}");

        return self::SUCCESS;
    }

    /** @return array<string, array<string,bool>> папка (inbox|spam) => коды из тем писем за сутки */
    private function probeFolders(array $cfg): array
    {
        [$host, $port] = array_pad(explode(':', (string) ($cfg['imap'] ?? 'imap.yandex.ru:993')), 2, '993');
        $client = (new ClientManager(['options' => ['fetch' => IMAP::FT_PEEK], 'decoding' => ['options' => ['header' => 'iconv']]]))->make([
            'host' => $host, 'port' => (int) $port, 'encryption' => 'ssl', 'validate_cert' => true,
            'username' => $cfg['imap_user'], 'password' => $cfg['imap_password'], 'protocol' => 'imap',
        ]);
        $client->connect();
        $out = ['inbox' => [], 'spam' => []];
        foreach ($client->getFolders(false) as $folder) {
            $kind = $folder->path === 'INBOX' ? 'inbox' : (preg_match('/spam|спам/iu', $folder->name . ' ' . $folder->path) ? 'spam' : null);
            if ($kind === null) {
                continue;
            }
            // Письмо, которое библиотека не разбирает (webklex: folder_path = null), не должно ронять проверку.
            try {
                $messages = $folder->query()->leaveUnread()->since(Carbon::now()->subDay())->setFetchBody(false)->get();
            } catch (\Throwable $e) {
                $this->warn("  папка {$folder->path}: " . mb_substr($e->getMessage(), 0, 120));
                continue;
            }
            foreach ($messages as $m) {
                try {
                    if (preg_match('/P\d{6}-\d+/', (string) $m->getSubject(), $mm)) {
                        $out[$kind][$mm[0]] = true;
                    }
                } catch (\Throwable) {
                }
            }
        }
        $client->disconnect();

        return $out;
    }

    /** Отказ (NDR) по контрольному адресу во входящих ящика-отправителя после отправки. */
    private function bounce(?Sender $s, string $to, Carbon $since): ?string
    {
        if (!$s || !$s->imap_server || !$s->imap_password) {
            return null;
        }
        try {
            $client = (new ClientManager(['options' => ['fetch' => IMAP::FT_PEEK], 'decoding' => ['options' => ['header' => 'iconv']]]))->make([
                'host' => $s->imap_server, 'port' => (int) ($s->imap_port ?: 993), 'encryption' => $s->imap_encryption ?: 'ssl',
                'validate_cert' => false, 'username' => $s->imap_user ?: $s->email, 'password' => $s->imap_password, 'protocol' => 'imap',
            ]);
            $client->connect();
            foreach ($client->getFolder('INBOX')->query()->leaveUnread()->since($since->copy()->startOfDay())->get() as $m) {
                $from = mb_strtolower((string) $m->getFrom()?->first()?->mail);
                $body = (string) ($m->getTextBody() ?: strip_tags((string) $m->getHTMLBody()));
                if ((str_contains($from, 'mailer-daemon') || str_contains($from, 'postmaster')) && str_contains(mb_strtolower($body), $to)) {
                    preg_match('/' . preg_quote($to, '/') . '\s+([^\n]{3,160})/i', $body, $mm);

                    return 'NDR: ' . trim($mm[1] ?? mb_substr(preg_replace('/\s+/', ' ', $body), 0, 120));
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }
}
