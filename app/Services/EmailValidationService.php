<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmailValidationService
{
    /**
     * Проверяет валидность email адреса
     *
     * @param string $email
     * @param string|null $provider API провайдер (neverbounce, emaillistverify, datavalidation)
     * @return array ['valid' => bool, 'reason' => string|null, 'provider' => string]
     */
    public function validate(string $email, ?string $provider = null): array
    {
        $email = strtolower(trim($email));

        // Проверяем кеш
        $cacheKey = "email_validation:{$email}";
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Базовая валидация
        $basicValidation = $this->basicValidation($email);
        if (!$basicValidation['valid']) {
            $this->cacheResult($email, $basicValidation);
            return $basicValidation;
        }

        // Проверка через API (если настроен)
        $apiValidation = $this->validateViaApi($email, $provider);
        if ($apiValidation) {
            $this->cacheResult($email, $apiValidation);
            return $apiValidation;
        }

        // Если API не настроен, возвращаем результат базовой проверки
        $this->cacheResult($email, $basicValidation);
        return $basicValidation;
    }

    /**
     * Базовая валидация email
     */
    private function basicValidation(string $email): array
    {
        // Проверка синтаксиса
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'valid' => false,
                'reason' => 'invalid_syntax',
                'provider' => 'basic'
            ];
        }

        // Проверка одноразовых email
        if ($this->isDisposableEmail($email)) {
            return [
                'valid' => false,
                'reason' => 'disposable_email',
                'provider' => 'basic'
            ];
        }

        // Проверка MX записей
        [$user, $domain] = explode('@', $email);
        if (!$this->checkMxRecords($domain)) {
            return [
                'valid' => false,
                'reason' => 'no_mx_records',
                'provider' => 'basic'
            ];
        }

        return [
            'valid' => true,
            'reason' => null,
            'provider' => 'basic'
        ];
    }

    /**
     * Проверка через внешний API
     */
    private function validateViaApi(string $email, ?string $provider = null): ?array
    {
        // Определяем провайдера
        $provider = $provider ?? config('services.email_validation_provider');
        if (!$provider) {
            return null;
        }

        try {
            switch ($provider) {
                case 'neverbounce':
                    return $this->validateViaNeverBounce($email);
                case 'emaillistverify':
                    return $this->validateViaEmailListVerify($email);
                case 'datavalidation':
                    return $this->validateViaDataValidation($email);
                default:
                    return null;
            }
        } catch (\Exception $e) {
            Log::warning('Email validation API error', [
                'email' => $email,
                'provider' => $provider,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Проверка через NeverBounce API
     */
    private function validateViaNeverBounce(string $email): ?array
    {
        $apiKey = config('services.neverbounce.api_key');
        if (!$apiKey) {
            return null;
        }

        $response = Http::get('https://api.neverbounce.com/v4/single/check', [
            'key' => $apiKey,
            'email' => $email
        ]);

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();
        $result = $data['result'] ?? 'unknown';

        return [
            'valid' => in_array($result, ['valid', 'catchall']),
            'reason' => $result === 'valid' ? null : $result,
            'provider' => 'neverbounce'
        ];
    }

    /**
     * Проверка через EmailListVerify API
     */
    private function validateViaEmailListVerify(string $email): ?array
    {
        $apiKey = config('services.emaillistverify.api_key');
        if (!$apiKey) {
            return null;
        }

        $response = Http::get('https://apps.emaillistverify.com/api/verifyEmail', [
            'secret' => $apiKey,
            'email' => $email
        ]);

        if (!$response->successful()) {
            return null;
        }

        $status = $response->body();

        return [
            'valid' => $status === 'ok',
            'reason' => $status === 'ok' ? null : $status,
            'provider' => 'emaillistverify'
        ];
    }

    /**
     * Проверка через DataValidation API
     */
    private function validateViaDataValidation(string $email): ?array
    {
        $apiKey = config('services.datavalidation.api_key');
        if (!$apiKey) {
            return null;
        }

        $response = Http::withHeaders([
            'Authorization' => "bearer {$apiKey}"
        ])->get('https://api.datavalidation.com/1.0/list', [
            'email' => $email
        ]);

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();
        $status = $data['status'] ?? 'unknown';

        return [
            'valid' => $status === 'valid',
            'reason' => $status === 'valid' ? null : $status,
            'provider' => 'datavalidation'
        ];
    }

    /**
     * Проверка MX записей домена
     */
    private function checkMxRecords(string $domain): bool
    {
        return checkdnsrr($domain, 'MX');
    }

    /**
     * Проверка на одноразовый email
     */
    private function isDisposableEmail(string $email): bool
    {
        $disposableDomains = [
            'tempmail.com', 'guerrillamail.com', '10minutemail.com',
            'mailinator.com', 'throwaway.email', 'temp-mail.org',
            'maildrop.cc', 'trashmail.com', 'yopmail.com',
            'getnada.com', 'fake-mail.com', 'mohmal.com'
        ];

        [$user, $domain] = explode('@', $email);
        return in_array($domain, $disposableDomains);
    }

    /**
     * Кеширование результата проверки
     */
    private function cacheResult(string $email, array $result): void
    {
        $cacheKey = "email_validation:{$email}";
        // Кешируем на 30 дней
        Cache::put($cacheKey, $result, now()->addDays(30));
    }

    /**
     * Массовая проверка email адресов
     */
    public function validateBulk(array $emails, ?string $provider = null): array
    {
        $results = [];

        foreach ($emails as $email) {
            $results[$email] = $this->validate($email, $provider);
        }

        return $results;
    }

    /**
     * Очистка кеша для email
     */
    public function clearCache(string $email): void
    {
        $email = strtolower(trim($email));
        $cacheKey = "email_validation:{$email}";
        Cache::forget($cacheKey);
    }
}
