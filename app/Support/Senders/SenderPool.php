<?php

namespace App\Support\Senders;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Пул ящика (reports.senders.pool): 'main' — основная рассылка, 'alias' — алгоритм «ящик на батч»
 * (docs/alias-mailbox-design.md). Пока колонки нет (миграция на прод-БД не накачена), фильтры — no-op:
 * ящиков 'alias' без колонки не бывает (AliasMailboxFactory без неё не создаёт).
 */
final class SenderPool
{
    public const MAIN = 'main';
    public const ALIAS = 'alias';

    private static ?bool $hasColumn = null;

    public static function hasColumn(): bool
    {
        return self::$hasColumn ??= (bool) Cache::remember('senders:has_pool_column', 600, static function () {
            try {
                return Schema::connection('reports')->hasColumn('senders', 'pool');
            } catch (\Throwable) {
                return false;
            }
        });
    }

    /** Только ящики основной рассылки (query builder; $col — с алиасом таблицы при join). */
    public static function onlyMain($query, string $col = 'pool')
    {
        if (self::hasColumn()) {
            $query->where(fn ($q) => $q->whereNull($col)->orWhere($col, self::MAIN));
        }

        return $query;
    }

    /** То же для сырого SQL: "AND (...)" или пусто. */
    public static function mainSql(string $col = 'pool'): string
    {
        return self::hasColumn() ? " AND ({$col} IS NULL OR {$col} = '" . self::MAIN . "')" : '';
    }

    /** @return array<int,true> id ящиков ветки «ящик на батч» */
    public static function aliasIds(): array
    {
        if (!self::hasColumn()) {
            return [];
        }

        return array_fill_keys(\Illuminate\Support\Facades\DB::connection('reports')->table('senders')
            ->where('pool', self::ALIAS)->pluck('id')->map(fn ($v) => (int) $v)->all(), true);
    }

    public static function forget(): void
    {
        self::$hasColumn = null;
        Cache::forget('senders:has_pool_column');
    }
}
