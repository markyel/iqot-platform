<?php

namespace App\Services\Generate;

use App\Services\Api\OpenAIClassifierClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Порт n8n-узлов «Get Token Template» + «Generate Token Prompt» +
 * «AI Generate Token» + «Clean Token».
 *
 * Генерит базовый трекинг-токен батча. Стиль токена берётся из
 * token_templates.prompt_template ОТПРАВИТЕЛЯ (senders.token_template_id) —
 * это и есть механизм различия стилей токенов между sender'ами
 * (АНТИ-ФИНГЕРПРИНТИНГ, НЕ упрощать до общего random). Программный фолбэк —
 * только когда шаблона нет, AI выключен или ответ невалиден.
 */
class CampaignTokenGenerator
{
    private const CONN = 'reports';

    private const SYSTEM_PROMPT = 'You are a tracking token generator. Generate ONLY the token string, '
        . 'without any explanation, quotes, markdown or extra text. Output a single token word.';

    public function __construct(
        private readonly OpenAIClassifierClient $ai,
        private readonly string $tokenModel,
        private readonly bool $useAi,
    ) {
    }

    /**
     * Возвращает базовый токен и проставляет его в $batch->trackingToken.
     */
    public function generate(Batch $batch): string
    {
        $template = $this->loadTokenTemplate($batch->sender['token_template_id'] ?? null);

        // Нет шаблона → программный фолбэк (порт ветки «!template.prompt_template»).
        if (!$template || empty($template['prompt_template'])) {
            $token = $this->fallbackToken();
            $batch->trackingToken = $token;
            return $token;
        }

        // AI выключен флагом → тоже фолбэк (но шаблон существует).
        if (!$this->useAi || !$this->ai->isConfigured()) {
            $token = $this->fallbackToken();
            $batch->trackingToken = $token;
            return $token;
        }

        $prompt = $this->buildPrompt((string) $template['prompt_template'], $batch);

        try {
            $raw = $this->ai->textCompletion($this->tokenModel, self::SYSTEM_PROMPT, $prompt, 100, 0.7);
            $token = $this->cleanToken($raw);
        } catch (\Throwable $e) {
            Log::warning('CampaignTokenGenerator: AI token failed, using fallback', [
                'error' => $e->getMessage(),
            ]);
            $token = $this->cleanToken(''); // пустой → FB-фолбэк внутри cleanToken
        }

        $batch->trackingToken = $token;
        return $token;
    }

    /**
     * Порт «Get Token Template».
     *
     * @return array<string,mixed>|null
     */
    private function loadTokenTemplate(mixed $tokenTemplateId): ?array
    {
        if (empty($tokenTemplateId)) {
            return null;
        }
        $row = DB::connection(self::CONN)->selectOne(
            'SELECT tt.id, tt.name, tt.prompt_template, tt.example
             FROM token_templates tt
             WHERE tt.id = ? AND tt.is_active = 1
             LIMIT 1',
            [(int) $tokenTemplateId]
        );
        return $row ? (array) $row : null;
    }

    /**
     * Порт «Generate Token Prompt»: подстановка {{items}}/{{company}}/{{index}}.
     */
    private function buildPrompt(string $template, Batch $batch): string
    {
        $items = $batch->items;
        $parts = [];
        foreach (array_slice($items, 0, 5) as $item) {
            $name = $item['name'] ?? '';
            $brand = $item['brand'] ?? '';
            $quantity = $item['quantity'] ?? 0;
            $unit = $item['unit'] ?? 'шт';
            $parts[] = trim("$name $brand - $quantity $unit");
        }
        $itemsText = implode('; ', $parts);

        $company = $batch->sender['sender_name'] ?? 'ЛифтСервис';
        if ($company === null || $company === '') {
            $company = 'ЛифтСервис';
        }

        $prompt = preg_replace('/\{\{items\}\}/', $itemsText, $template);
        $prompt = preg_replace('/\{\{company\}\}/', (string) $company, $prompt);
        $prompt = preg_replace('/\{\{index\}\}/', '1', $prompt);

        return (string) $prompt;
    }

    /**
     * Порт «Clean Token»: срезает markdown/кавычки/переносы, берёт первое слово,
     * валидирует 3–200 симв., иначе FB-фолбэк.
     */
    private function cleanToken(string $raw): string
    {
        $token = trim($raw);
        $token = preg_replace('/```[a-z]*\n?/i', '', $token) ?? '';
        $token = preg_replace('/["\'`]/', '', $token) ?? '';
        $token = preg_replace('/\n+/', ' ', $token) ?? '';
        $token = trim($token);
        $parts = preg_split('/\s+/', $token, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $token = $parts[0] ?? '';

        $len = mb_strlen($token);
        if ($token === '' || $len < 3 || $len > 200) {
            return $this->fallbackToken('FB-');
        }
        return $token;
    }

    /**
     * Программный токен `[FB-]PREFIX-MMDD-RAND` (порт фолбэков n8n).
     */
    private function fallbackToken(string $prefixTag = ''): string
    {
        // 3 случайных base36-символа в верхнем регистре (аналог toString(36).substring(2,5)).
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $prefix = '';
        for ($i = 0; $i < 3; $i++) {
            $prefix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $date = date('md'); // MMDD (ISO slice(5,10).replace('-','')).
        $random = random_int(1000, 9999);

        return $prefixTag . "{$prefix}-{$date}-{$random}";
    }
}
