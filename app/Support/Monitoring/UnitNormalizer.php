<?php

namespace App\Support\Monitoring;

/**
 * Единица измерения → piece|meter|kg|liter|set|pack|unknown.
 */
final class UnitNormalizer
{
    public const UNITS = ['piece', 'meter', 'kg', 'liter', 'set', 'pack', 'unknown'];

    public static function normalize(?string $unit): string
    {
        $u = mb_strtolower(trim((string) $unit));
        $u = rtrim($u, '.');
        if ($u === '') {
            return 'unknown';
        }
        if (in_array($u, self::UNITS, true)) {
            return $u;
        }

        return match (true) {
            (bool) preg_match('/^(шт|штук[аи]?|ед|единиц[аы]?|pcs|pc|piece|ea|each)$/u', $u) => 'piece',
            (bool) preg_match('/^(м|метр[аов]*|м\.?\s?п|п\.?\s?м|пог\.?\s?м|погонн\w*\s?метр\w*|m|meter|metre)$/u', $u) => 'meter',
            (bool) preg_match('/^(кг|килограмм\w*|kg)$/u', $u) => 'kg',
            (bool) preg_match('/^(л|литр\w*|l|liter|litre)$/u', $u) => 'liter',
            (bool) preg_match('/^(компл|комплект\w*|к-т|кт|набор\w*|set|kit)$/u', $u) => 'set',
            (bool) preg_match('/^(уп|упак\w*|пач\w*|pack|pkg)$/u', $u) => 'pack',
            default => 'unknown',
        };
    }
}
