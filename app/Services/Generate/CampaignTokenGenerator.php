<?php

namespace App\Services\Generate;

use App\Services\Api\OpenAIClassifierClient;
use Illuminate\Support\Facades\DB;

/**
 * Генерит базовый трекинг-токен батча (матчинг ответов ищет его в теме+теле).
 *
 * ДИЗАЙН (2026-07): токен = КОРОТКИЙ естественный реф (5 симв., буква+цифры, стиль
 * «S8273»/«R4821»), а не длинный AI-хеш — длинный код в теме = спам-сигнал у почтовых
 * фильтров. Стиль рефа СТАБИЛЕН и РАЗЛИЧАЕТСЯ per-sender (анти-фингерпринт: 6 стилей,
 * ключ — token_template_id ящика). Обязательно с буквой — чисто цифровой реф мог бы
 * случайно совпасть с ценой/количеством в ответе поставщика (ложный матч). Уникальность
 * гарантируется проверкой по окну 90 дней (ретрай при коллизии). AI больше не зовём.
 */
class CampaignTokenGenerator
{
    private const CONN = 'reports';

    // Без визуально спорных символов (O/0, I/1/L) — читается как настоящий артикул/№.
    private const LETTERS = 'ABCDEFGHKMNPRSTUVXYZ';
    private const DIGITS = '23456789';

    // AI/model больше не используются для контента токена (короткий стилевой реф
    // генерится детерминированно), но конструктор сохраняет сигнатуру — его зовёт
    // GenerateCampaignJob с (client, model, useAi).
    public function __construct(
        private readonly OpenAIClassifierClient $ai,
        private readonly string $tokenModel = 'gpt-4o-mini',
        private readonly bool $useAi = false,
    ) {
    }

    /**
     * Возвращает базовый токен и проставляет его в $batch->trackingToken.
     */
    public function generate(Batch $batch): string
    {
        $styleKey = (int) ($batch->sender['token_template_id'] ?? ($batch->sender['id'] ?? 0));
        $style = $styleKey % 6;

        $token = $this->makeToken($style);
        for ($i = 0; $i < 10 && $this->exists($token); $i++) {
            $token = $this->makeToken($style);
        }

        $batch->trackingToken = $token;
        return $token;
    }

    /**
     * Короткий реф в одном из 6 per-sender стилей (5 значимых символов, буква+цифры).
     */
    private function makeToken(int $style): string
    {
        $L = fn () => self::LETTERS[random_int(0, strlen(self::LETTERS) - 1)];
        $D = fn () => self::DIGITS[random_int(0, strlen(self::DIGITS) - 1)];
        $d = fn (int $n) => implode('', array_map(static fn () => self::DIGITS[random_int(0, strlen(self::DIGITS) - 1)], range(1, $n)));

        return match ($style) {
            0 => $L() . $d(4),              // R4821  — буква + 4 цифры (как «S8273»)
            1 => $L() . $L() . $d(3),       // SK482  — 2 буквы + 3 цифры
            2 => $d(4) . $L(),              // 4821K  — 4 цифры + буква
            3 => $L() . '-' . $d(3),        // K-482  — буква-дефис-цифры
            4 => $d(2) . $L() . $d(2),      // 48R21  — цифры-буква-цифры
            default => $L() . $d(2) . $L() . $D(), // R48K2 — смешанный
        };
    }

    /**
     * Занят ли токен среди батчей за последние 90 дней (окно матчинга — 60 дней,
     * берём с запасом). Substring-коллизий на 5 символах с буквой практически нет.
     */
    private function exists(string $token): bool
    {
        return DB::connection(self::CONN)->table('email_batches')
            ->where('tracking_token', $token)
            ->where('created_at', '>=', now()->subDays(90))
            ->exists();
    }
}
