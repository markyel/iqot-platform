<?php

namespace App\Services\Senders;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Персональный отправитель клиента (именные заявки из кабинета) — напрямую в reports.senders /
 * client_organizations / reserved_emails. Заменяет n8n-воркфлоу «sender-management» (n8n выключен 10.2026)
 * с теми же форматами ответов, что ждут контроллеры и шаблоны.
 *
 * Ящик берётся из пула reports.reserved_emails (is_active=1, is_assigned=0) и помечается занятым.
 * Отправитель — is_personal=1, user_id = id пользователя iqot, pool=main: CampaignSenderAssigner шлёт им
 * заявки его организации.
 */
class CustomerSenderService
{
    private const CONN = 'reports';
    private const ORG_FIELDS = ['name', 'inn', 'kpp', 'legal_address', 'contact_person', 'phone', 'email'];

    public function getAvailableEmails(): array
    {
        $emails = DB::connection(self::CONN)->table('reserved_emails')->where('is_active', 1)->where('is_assigned', 0)
            ->orderBy('email')->get(['id', 'email'])->map(fn ($r) => (array) $r)->all();

        return ['success' => true, 'emails' => $emails];
    }

    public function getEmailTemplates(): array
    {
        $exclude = array_map('intval', (array) config('services.sender_import.template_exclude_ids', []));
        $templates = DB::connection(self::CONN)->table('email_templates')->where('is_active', 1)
            ->when($exclude !== [], fn ($q) => $q->whereNotIn('id', $exclude))
            ->orderBy('name')->get(['id', 'name'])->map(fn ($r) => (array) $r)->all();

        return ['success' => true, 'templates' => $templates];
    }

    public function getUserSender(int $userId): ?array
    {
        return $this->getUserSenderWithOrganization($userId)['sender'];
    }

    /** @return array{success: bool, sender: ?array, organization: ?array} */
    public function getUserSenderWithOrganization(int $userId): array
    {
        $db = DB::connection(self::CONN);
        $user = User::find($userId);
        $sender = null;
        if ($user?->sender_id) {
            $sender = $db->table('senders')->where('id', $user->sender_id)->first();
        }
        $sender ??= $db->table('senders')->where('user_id', $userId)->where('is_personal', 1)->orderByDesc('is_active')->orderByDesc('id')->first();
        $orgId = $sender->client_organization_id ?? $user?->client_organization_id;
        $org = $orgId ? $db->table('client_organizations')->where('id', $orgId)->first() : null;

        return [
            'success' => true,
            'sender' => $sender ? $this->publicSender($sender) : null,
            'organization' => $org ? (array) $org : null,
        ];
    }

    /**
     * @param array<string,mixed> $data reserved_email_id, template_id, sender_name, sender_full_name, phone, organization[...]
     * @return array{success: bool, sender_id?: int, client_organization_id?: int, message?: string}
     */
    public function createSender(int $userId, array $data): array
    {
        $db = DB::connection(self::CONN);
        try {
            return $db->transaction(function () use ($db, $userId, $data) {
                $mailbox = $db->table('reserved_emails')->where('id', (int) $data['reserved_email_id'])
                    ->where('is_active', 1)->where('is_assigned', 0)->lockForUpdate()->first();
                if (!$mailbox) {
                    return ['success' => false, 'message' => 'Ящик уже занят или отключён — выберите другой'];
                }
                $now = now();
                $orgId = (int) $db->table('client_organizations')->insertGetId(
                    $this->orgAttributes((array) ($data['organization'] ?? [])) + ['is_active' => 1, 'created_at' => $now, 'updated_at' => $now]
                );
                $enc = in_array($mailbox->encryption, ['ssl', 'tls'], true) ? $mailbox->encryption : 'ssl';
                $tokenTemplates = $db->table('token_templates')->where('is_active', 1)->pluck('id')->all();
                $senderId = (int) $db->table('senders')->insertGetId([
                    'sender_name' => $data['sender_name'],
                    'sender_full_name' => $data['sender_full_name'],
                    'phone' => $data['phone'] ?? null,
                    'phone_normalized' => self::normalizePhone($data['phone'] ?? null),
                    'email' => $mailbox->email,
                    'smtp_server' => $mailbox->smtp_server,
                    'smtp_port' => $mailbox->smtp_port,
                    'smtp_user' => $mailbox->username ?: $mailbox->email,
                    'smtp_password' => $mailbox->password,
                    'smtp_encryption' => $enc,
                    'imap_server' => $mailbox->imap_server,
                    'imap_port' => $mailbox->imap_port,
                    'imap_user' => $mailbox->username ?: $mailbox->email,
                    'imap_password' => $mailbox->password,
                    'imap_encryption' => $enc,
                    'client_organization_id' => $orgId,
                    'token_template_id' => $tokenTemplates !== [] ? (int) $tokenTemplates[array_rand($tokenTemplates)] : 1,
                    'preferred_template_id' => !empty($data['template_id']) ? (int) $data['template_id'] : null,
                    'daily_limit' => 60,
                    'is_active' => 1,
                    'is_verified' => 0,
                    'is_personal' => 1,
                    'pool' => 'main',
                    'user_id' => $userId,
                    'email_style' => 'formal',
                    'email_greeting' => 'Здравствуйте',
                    'token_style' => 'technical',
                    'send_delay_seconds' => 20,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $db->table('reserved_emails')->where('id', $mailbox->id)
                    ->update(['is_assigned' => 1, 'assigned_to_sender_id' => $senderId, 'assigned_at' => $now, 'updated_at' => $now]);

                return ['success' => true, 'sender_id' => $senderId, 'client_organization_id' => $orgId];
            });
        } catch (\Throwable $e) {
            Log::error('CustomerSenderService: create failed', ['user_id' => $userId, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Ошибка создания отправителя: ' . $e->getMessage()];
        }
    }

    /** @param array<string,mixed> $data template_id, sender_name, sender_full_name, phone, organization[...] */
    public function updateSender(int $senderId, array $data): array
    {
        $db = DB::connection(self::CONN);
        $sender = $db->table('senders')->where('id', $senderId)->first(['id', 'client_organization_id']);
        if (!$sender) {
            return ['success' => false, 'message' => 'Отправитель не найден'];
        }
        $db->transaction(function () use ($db, $sender, $data) {
            $upd = ['updated_at' => now()];
            foreach (['sender_name', 'sender_full_name', 'phone'] as $k) {
                if (array_key_exists($k, $data)) {
                    $upd[$k] = $data[$k];
                }
            }
            if (array_key_exists('phone', $data)) {
                $upd['phone_normalized'] = self::normalizePhone($data['phone']);
            }
            if (!empty($data['template_id'])) {
                $upd['preferred_template_id'] = (int) $data['template_id'];
            }
            $db->table('senders')->where('id', $sender->id)->update($upd);
            if ($sender->client_organization_id && isset($data['organization'])) {
                $db->table('client_organizations')->where('id', $sender->client_organization_id)
                    ->update($this->orgAttributes((array) $data['organization']) + ['updated_at' => now()]);
            }
        });

        return ['success' => true];
    }

    public function deactivateSender(int $senderId): bool
    {
        return DB::connection(self::CONN)->table('senders')->where('id', $senderId)->update(['is_active' => 0, 'updated_at' => now()]) > 0;
    }

    private function orgAttributes(array $org): array
    {
        $out = [];
        foreach (self::ORG_FIELDS as $f) {
            if (array_key_exists($f, $org)) {
                $v = trim((string) ($org[$f] ?? ''));
                $out[$f] = $v === '' ? null : $v;
            }
        }
        if (array_key_exists('legal_address', $out)) {
            $out['actual_address'] = $out['legal_address'];
        }
        foreach (['inn' => [10, 12], 'kpp' => [9]] as $f => $lens) {
            if (isset($out[$f])) {
                $digits = preg_replace('/\D+/', '', $out[$f]);
                $out[$f] = in_array(strlen($digits), $lens, true) ? $digits : null;
            }
        }

        return $out;
    }

    /** Без паролей — в шаблоны и логи не попадают. */
    private function publicSender(object $s): array
    {
        $a = (array) $s;
        unset($a['smtp_password'], $a['imap_password']);
        $a['template_id'] = $a['preferred_template_id'] ?? $a['template_id'] ?? null;

        return $a;
    }

    private static function normalizePhone(?string $phone): ?string
    {
        $n = trim((string) preg_replace('/[^\d,]+/', '', (string) $phone), ',');

        return $n === '' ? null : mb_substr($n, 0, 20);
    }
}
