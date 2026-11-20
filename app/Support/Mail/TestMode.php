<?php

namespace App\Support\Mail;

/**
 * Тестовый режим почты (20.11.2026, после восстановления прода): задан EMAILS_TEST_RECIPIENTS — наружу уходят
 * письма ТОЛЬКО на эти адреса. Рассылка (email_queue) → cancelled, ответы поставщикам (outgoing_replies) → failed,
 * почта Laravel — отменяется; ящик и получатель не штрафуются. Проверка стоит в задачах отправки и, последним
 * рубежом, в самих отправщиках перед SMTP/релеем. Пустой список — обычная работа.
 */
final class TestMode
{
    public const BLOCKED = 'test mode: recipient not allowed';

    /** @return string[] адреса в нижнем регистре */
    public static function recipients(): array
    {
        return array_values(array_filter(array_map(
            static fn ($e) => mb_strtolower(trim((string) $e)),
            (array) config('services.email_test.recipients', []),
        )));
    }

    public static function active(): bool
    {
        return self::recipients() !== [];
    }

    public static function allows(?string $email): bool
    {
        return !self::active() || in_array(mb_strtolower(trim((string) $email)), self::recipients(), true);
    }

    /** Последний рубеж в отправщиках: адрес не из списка — исключение, письмо не уходит. */
    public static function assertAllowed(?string $email): void
    {
        if (!self::allows($email)) {
            throw new \RuntimeException(self::BLOCKED . ': ' . $email);
        }
    }
}
