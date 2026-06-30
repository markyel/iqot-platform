<?php

namespace App\Models\Reports;

use Illuminate\Database\Eloquent\Model;

/**
 * Заблокированный домен получателя (reports.blocked_domains) — кому рассылку НЕ шлём
 * совсем (жалобы на спам, явный отказ домена и т.п.).
 *
 * Доменный аналог per-адресного recipient_mailboxes.is_blocked: блок на уровне
 * ГЕНЕРАЦИИ — CampaignSupplierSelector исключает поставщиков, чей домен email здесь.
 * Хранится нормализованным (нижний регистр, без «@»).
 */
class BlockedDomain extends Model
{
    protected $connection = 'reports';

    protected $table = 'blocked_domains';

    protected $fillable = ['domain', 'reason'];

    /** Нормализовать домен: нижний регистр, обрезать пробелы и ведущий «@». */
    public static function normalize(string $domain): string
    {
        return ltrim(mb_strtolower(trim($domain)), '@');
    }

    /** Добавить/обновить домен в блок-листе (идемпотентно по уникальному domain). */
    public static function block(string $domain, ?string $reason = null): void
    {
        $d = self::normalize($domain);
        if ($d === '') {
            return;
        }

        self::query()->updateOrCreate(['domain' => $d], ['reason' => $reason]);
    }

    /** Заблокирован ли домен данного email-адреса. */
    public static function isBlocked(string $email): bool
    {
        $at = strrpos($email, '@');
        $domain = mb_strtolower($at !== false ? substr($email, $at + 1) : $email);

        return $domain !== '' && self::query()->where('domain', $domain)->exists();
    }
}
