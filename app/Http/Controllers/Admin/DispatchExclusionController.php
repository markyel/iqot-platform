<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reports\BlockedDomain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Стоп-лист рассылки: ручное исключение доменов и ящиков получателей.
 *
 * Три уровня (см. также авто-механизмы):
 *   - blocked_domains — доменный блок на ГЕНЕРАЦИИ (CampaignSupplierSelector);
 *   - recipient_mailboxes.is_blocked — per-адрес блок на ОТПРАВКЕ;
 *   - suppliers.is_active/notify_email — точечное отключение поставщика на генерации.
 * «Блок ящика» здесь делает всё сразу (адрес + поставщик + отмена pending),
 * зеркало ручного подавления жалобщика.
 */
class DispatchExclusionController extends Controller
{
    private const CONN = 'reports';

    public function index(): View
    {
        $domains = DB::connection(self::CONN)->table('blocked_domains')
            ->orderByDesc('id')->get();

        // Заблокированные ящики + (если есть) деактивированный поставщик того же адреса.
        $mailboxes = DB::connection(self::CONN)->table('recipient_mailboxes as rm')
            ->leftJoin('suppliers as s', DB::raw('LOWER(s.email)'), '=', DB::raw('LOWER(rm.email)'))
            ->where('rm.is_blocked', 1)
            ->orderByDesc('rm.blocked_at')
            ->get(['rm.email', 'rm.blocked_at', 'rm.last_error_message', 's.id as supplier_id', 's.name as supplier_name', 's.is_active as supplier_active']);

        return view('admin.exclusions.index', [
            'domains' => $domains,
            'mailboxes' => $mailboxes,
        ]);
    }

    /** Добавить домен в блок-лист + отменить его pending-письма. */
    public function storeDomain(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'domain' => 'required|string|max:255',
            'reason' => 'nullable|string|max:255',
        ], [
            'domain.required' => 'Укажите домен.',
        ]);

        $domain = BlockedDomain::normalize($data['domain']);
        if ($domain === '' || !preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain)) {
            return back()->with('error', 'Некорректный домен: ' . $data['domain']);
        }

        BlockedDomain::block($domain, $data['reason'] ?? 'manual (admin)');
        $cancelled = $this->cancelPending(fn ($q) => $q->whereRaw("SUBSTRING_INDEX(LOWER(to_email), '@', -1) = ?", [$domain]));

        return back()->with('status', "Домен {$domain} добавлен в стоп-лист. Отменено pending-писем: {$cancelled}.");
    }

    public function destroyDomain(Request $request): RedirectResponse
    {
        $data = $request->validate(['domain' => 'required|string|max:255']);
        $domain = BlockedDomain::normalize($data['domain']);

        DB::connection(self::CONN)->table('blocked_domains')->where('domain', $domain)->delete();

        return back()->with('status', "Домен {$domain} убран из стоп-листа. Рассылка на него снова возможна.");
    }

    /** Заблокировать ящик: адрес (отправка) + поставщик (генерация) + отмена pending. */
    public function storeMailbox(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => 'required|email|max:255',
            'reason' => 'nullable|string|max:255',
        ], [
            'email.required' => 'Укажите email.',
            'email.email' => 'Некорректный email.',
        ]);

        $email = mb_strtolower(trim($data['email']));
        $reason = $data['reason'] ?? 'manual (admin)';
        $now = now();

        // 1) per-адрес блок на отправке (создаём строку, если её ещё нет).
        DB::connection(self::CONN)->table('recipient_mailboxes')->updateOrInsert(
            ['email' => $email],
            ['is_blocked' => 1, 'blocked_at' => $now, 'last_error_message' => $reason, 'updated_at' => $now],
        );

        // 2) деактивируем поставщика(ов) с этим адресом — исключаем из генерации.
        $sup = DB::connection(self::CONN)->table('suppliers')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->update(['is_active' => 0, 'notify_email' => 0, 'updated_at' => $now]);

        // 3) отменяем уже стоящие в очереди письма этому адресу.
        $cancelled = $this->cancelPending(fn ($q) => $q->whereRaw('LOWER(to_email) = ?', [$email]));

        return back()->with('status', "Ящик {$email} заблокирован (поставщиков деактивировано: {$sup}, отменено pending: {$cancelled}).");
    }

    /** Разблокировать ящик: снять блок адреса + реактивировать поставщика. */
    public function destroyMailbox(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|email|max:255']);
        $email = mb_strtolower(trim($data['email']));
        $now = now();

        DB::connection(self::CONN)->table('recipient_mailboxes')->where('email', $email)
            ->update(['is_blocked' => 0, 'blocked_at' => null, 'updated_at' => $now]);

        DB::connection(self::CONN)->table('suppliers')->whereRaw('LOWER(email) = ?', [$email])
            ->update(['is_active' => 1, 'notify_email' => 1, 'updated_at' => $now]);

        return back()->with('status', "Ящик {$email} разблокирован, поставщик реактивирован.");
    }

    /** Отменить pending-письма по условию (status pending → cancelled). */
    private function cancelPending(\Closure $where): int
    {
        $q = DB::connection(self::CONN)->table('email_queue')->where('status', 'pending');
        $where($q);

        return $q->update([
            'status' => 'cancelled',
            'error_message' => 'manual exclusion (admin)',
            'updated_at' => now(),
        ]);
    }
}
