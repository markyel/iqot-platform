<?php

namespace App\Models\Reports;

use Illuminate\Database\Eloquent\Model;

/**
 * Ящик получателя (reports.recipient_mailboxes) — учёт ошибок отправки подряд.
 *
 * Ключ — нормализованный e-mail (lowercase, trim). При неуспешной отправке (не
 * ratelimit) счётчик растёт, при успешной — сбрасывается. По достижении порога
 * ящик помечается is_blocked → диспетчер (DispatchPendingEmails) и сам джоб
 * (SendQueuedEmailJob) перестают слать на него письма. Разблокировка — ручная.
 */
class RecipientMailbox extends Model
{
    protected $connection = 'reports';
    protected $table = 'recipient_mailboxes';

    protected $fillable = [
        'email',
        'consecutive_errors',
        'is_blocked',
        'last_error_message',
        'last_error_at',
        'last_success_at',
        'blocked_at',
    ];

    protected $casts = [
        'consecutive_errors' => 'integer',
        'is_blocked' => 'boolean',
        'last_error_at' => 'datetime',
        'last_success_at' => 'datetime',
        'blocked_at' => 'datetime',
    ];

    private static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * Успешная отправка — снять метку и обнулить счётчик ошибок.
     */
    public static function recordSuccess(string $email): void
    {
        $email = self::normalize($email);
        if ($email === '') {
            return;
        }

        self::query()->updateOrCreate(
            ['email' => $email],
            [
                'consecutive_errors' => 0,
                'is_blocked' => false,
                'blocked_at' => null,
                'last_success_at' => now(),
            ],
        );
    }

    /**
     * Неуспешная отправка — инкремент счётчика; при достижении порога — блок.
     */
    public static function recordFailure(string $email, string $message, int $threshold): void
    {
        $email = self::normalize($email);
        if ($email === '') {
            return;
        }

        $row = self::query()->firstOrNew(['email' => $email]);
        $row->consecutive_errors = (int) $row->consecutive_errors + 1;
        $row->last_error_message = mb_substr($message, 0, 255);
        $row->last_error_at = now();

        if ($row->consecutive_errors >= max(1, $threshold)) {
            $row->is_blocked = true;
            $row->blocked_at = now();
        }

        $row->save();
    }

    public static function isBlocked(string $email): bool
    {
        $email = self::normalize($email);
        if ($email === '') {
            return false;
        }

        return self::query()->where('email', $email)->where('is_blocked', true)->exists();
    }
}
