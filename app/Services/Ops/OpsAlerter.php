<?php

namespace App\Services\Ops;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Служебные оповещения в Telegram (бот IQOT). Одна и та же проблема (ключ) — не чаще раза в
 * repeat_minutes; когда проверка снова проходит — «восстановлено». Без токена/чата — только лог.
 */
class OpsAlerter
{
    public function isConfigured(): bool
    {
        return $this->token() !== '' && $this->chatId() !== '';
    }

    /** Проблема есть: оповестить (с антиповтором). */
    public function alert(string $key, string $text): void
    {
        Log::critical('OPS ALERT: ' . $key, ['text' => $text]);
        $repeat = (int) config('services.ops_alerts.repeat_minutes', 60);
        Cache::forever('ops:alert:active:' . $key, now()->toIso8601String());
        if (!Cache::add('ops:alert:sent:' . $key, 1, now()->addMinutes(max(1, $repeat)))) {
            return;
        }
        $this->send("🔴 <b>IQOT: проблема</b>\n" . $text);
    }

    /** Проверка прошла: если по ключу была тревога — «восстановлено». */
    public function resolve(string $key, string $text): void
    {
        if (!Cache::has('ops:alert:active:' . $key)) {
            return;
        }
        Cache::forget('ops:alert:active:' . $key);
        Cache::forget('ops:alert:sent:' . $key);
        Log::info('OPS RESOLVED: ' . $key);
        $this->send("🟢 <b>IQOT: восстановлено</b>\n" . $text);
    }

    public function send(string $html): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }
        try {
            $r = Http::timeout(10)->post('https://api.telegram.org/bot' . $this->token() . '/sendMessage', [
                'chat_id' => $this->chatId(),
                'text' => mb_substr($html, 0, 3900),
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);
            if (!$r->successful()) {
                Log::warning('OpsAlerter: Telegram не принял сообщение', ['status' => $r->status(), 'body' => mb_substr($r->body(), 0, 300)]);
            }

            return $r->successful();
        } catch (\Throwable $e) {
            Log::warning('OpsAlerter: Telegram недоступен', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function token(): string
    {
        return trim((string) config('services.ops_alerts.telegram_bot_token'));
    }

    private function chatId(): string
    {
        return trim((string) config('services.ops_alerts.telegram_chat_id'));
    }
}
