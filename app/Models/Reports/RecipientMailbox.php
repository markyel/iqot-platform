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
 *
 * Отбойники (NDR, входящие письма о недоставке) ведут ОТДЕЛЬНЫЙ счётчик
 * bounce_count: успешный SMTP-handshake (recordSuccess) ≠ доставка, поэтому он
 * не сбрасывает отбойники и не разблокирует ящик, заваленный отбойниками
 * (см. recordSuccess/recordBounce).
 */
class RecipientMailbox extends Model
{
    protected $connection = 'reports';
    protected $table = 'recipient_mailboxes';

    protected $fillable = [
        'email',
        'consecutive_errors',
        'bounce_count',
        'is_blocked',
        'last_error_message',
        'last_bounce_message',
        'last_error_at',
        'last_bounce_at',
        'last_success_at',
        'last_dispatched_at',
        'blocked_at',
    ];

    protected $casts = [
        'consecutive_errors' => 'integer',
        'bounce_count' => 'integer',
        'is_blocked' => 'boolean',
        'last_error_at' => 'datetime',
        'last_bounce_at' => 'datetime',
        'last_success_at' => 'datetime',
        'last_dispatched_at' => 'datetime',
        'blocked_at' => 'datetime',
    ];

    private static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * Успешная отправка — обнулить счётчик ошибок отправки.
     *
     * Ящик, заблокированный по ОТБОЙНИКАМ (bounce_count > 0), НЕ разблокируем:
     * принятие письма SMTP-сервером ≠ доставка получателю, отбойник прилетит
     * позже. Иначе каждая «успешная» отправка снимала бы блок битого адреса.
     */
    public static function recordSuccess(string $email): void
    {
        $email = self::normalize($email);
        if ($email === '') {
            return;
        }

        $row = self::query()->firstOrNew(['email' => $email]);
        $row->consecutive_errors = 0;
        $row->last_success_at = now();

        if ((int) $row->bounce_count === 0) {
            $row->is_blocked = false;
            $row->blocked_at = null;
        }

        $row->save();
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

    /**
     * Получен отбойник (NDR) для адреса — инкремент отдельного bounce_count;
     * при достижении порога подряд ящик блокируется (больше не шлём).
     *
     * Счётчик не сбрасывается удачной отправкой (см. recordSuccess) — чтобы
     * «критическая масса» отбойников реально набиралась и адрес отключался.
     */
    public static function recordBounce(string $email, string $message, int $threshold): void
    {
        $email = self::normalize($email);
        if ($email === '') {
            return;
        }

        $row = self::query()->firstOrNew(['email' => $email]);
        $row->bounce_count = (int) $row->bounce_count + 1;
        $row->last_bounce_message = mb_substr($message, 0, 255);
        $row->last_bounce_at = now();

        if ($row->bounce_count >= max(1, $threshold)) {
            $row->is_blocked = true;
            $row->blocked_at = now();
        }

        $row->save();
    }

    /**
     * Зафиксировать момент раздачи письма получателю (адаптивный пейсинг).
     *
     * Ставится диспетчером при клейме письма (status→sending), а НЕ в момент
     * успешной отправки: между тиком и асинхронным SendQueuedEmailJob иначе была
     * бы гонка — следующий тик мог бы выдать получателю второе письмо до того,
     * как первое реально ушло. Upsert по нормализованному email; счётчики
     * ошибок/отбойников не трогаем.
     */
    public static function markDispatched(string $email): void
    {
        $email = self::normalize($email);
        if ($email === '') {
            return;
        }

        $now = now();
        self::query()->updateOrCreate(
            ['email' => $email],
            ['last_dispatched_at' => $now],
        );
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
