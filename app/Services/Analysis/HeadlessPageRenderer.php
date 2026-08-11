<?php

namespace App\Services\Analysis;

use Illuminate\Support\Facades\Log;
use Spatie\Browsershot\Browsershot;

/**
 * Headless-рендер страницы через Chromium (Spatie Browsershot) — замена Tavily для
 * сайтов, отдающих контент только после исполнения JS (например, Beget-антибот:
 * первый HTTP-ответ — заглушка с set_cookie()+location.reload(), цены появляются
 * лишь после реального запуска браузера).
 *
 * Возвращает innerText страницы (видимый текст) или null при любой ошибке/таймауте —
 * вызывающий код продолжает на том, что есть. Метод screenshot() отдаёт PNG-байты
 * (используется админ-инструментом внешнего теста рендера).
 *
 * Прод-нюанс: воркеры крутятся под www-data, чей HOME (/var/www) не пишется. Chrome
 * без писчего HOME падает (crashpad / mkdir ~/.local). Поэтому HOME принудительно
 * переводим на writable-каталог в storage ДО запуска Browsershot (Symfony Process
 * наследует env родителя; setEnvironmentOptions HOME для chrome не перекрывает).
 */
class HeadlessPageRenderer
{
    public function __construct(
        private readonly string $chromePath = '/usr/bin/google-chrome-stable',
        private readonly string $homeDir = '',
        private readonly int $timeout = 30,
        private readonly string $userAgent = '',
    ) {
    }

    /**
     * Собирает рендерер из конфига services.email_analysis (те же значения, что
     * использует боевой AnalyzeSupplierReplyJob).
     */
    public static function fromConfig(): self
    {
        $ec = config('services.email_analysis', []);

        return new self(
            (string) ($ec['headless_chrome_path'] ?? '/usr/bin/google-chrome-stable'),
            (string) ($ec['headless_home'] ?? ''),
            (int) ($ec['headless_timeout'] ?? 30),
            (string) ($ec['user_agent'] ?? ''),
        );
    }

    /**
     * Видимый текст страницы (document.body.innerText), схлопнутые пробелы. Null при ошибке.
     */
    public function render(string $url): ?string
    {
        $browsershot = $this->boot($url);
        if ($browsershot === null) {
            return null;
        }

        try {
            $text = $browsershot->evaluate('document.body.innerText');
        } catch (\Throwable $e) {
            $this->logFailure('render', $url, $e);

            return null;
        }

        if (!is_string($text)) {
            return null;
        }

        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);

        return $text === '' ? null : $text;
    }

    /**
     * PNG-скриншот страницы (сырые байты) или null при ошибке.
     *
     * @param bool $fullPage true — вся высота прокрутки; false — только вьюпорт.
     */
    public function screenshot(string $url, bool $fullPage = true): ?string
    {
        $browsershot = $this->boot($url);
        if ($browsershot === null) {
            return null;
        }

        try {
            $browsershot->windowSize(1280, 900);
            if ($fullPage) {
                $browsershot->fullPage();
            }
            $bytes = $browsershot->screenshot();
        } catch (\Throwable $e) {
            $this->logFailure('screenshot', $url, $e);

            return null;
        }

        return is_string($bytes) && $bytes !== '' ? $bytes : null;
    }

    /**
     * Готовит писчий HOME для Chrome, ставит env и возвращает сконфигурированный
     * Browsershot на url. Null — если url невалиден или HOME недоступен.
     */
    private function boot(string $url): ?Browsershot
    {
        $url = trim($url);
        if (!preg_match('#^https?://#i', $url)) {
            return null;
        }

        $home = $this->prepareHome();
        if ($home === null) {
            return null;
        }

        // Symfony Process наследует env родителя; для www-data это единственный
        // способ дать Chrome писчий HOME (setEnvironmentOptions не перекрывает HOME).
        putenv('HOME=' . $home);
        $_SERVER['HOME'] = $home;
        $_ENV['HOME'] = $home;

        $browsershot = Browsershot::url($url)
            ->setChromePath($this->chromePath)
            ->noSandbox()
            ->setOption('args', [
                '--disable-dev-shm-usage',
                '--disable-gpu',
                '--user-data-dir=' . $home . '/profile',
                '--crash-dumps-dir=' . $home . '/crash',
            ])
            ->timeout($this->timeout)
            ->waitUntilNetworkIdle();

        // Дефолтный headless-UA содержит «HeadlessChrome» → часть сайтов режет.
        // Выдаём обычный десктопный Chrome (если задан в конфиге).
        if ($this->userAgent !== '') {
            $browsershot->userAgent($this->userAgent);
        }

        return $browsershot;
    }

    private function logFailure(string $op, string $url, \Throwable $e): void
    {
        Log::warning('HeadlessPageRenderer: ' . $op . ' failed', [
            'url' => mb_substr($url, 0, 200),
            'error' => mb_substr($e->getMessage(), 0, 300),
        ]);
    }

    /**
     * Гарантирует писчий HOME-каталог для Chrome. Null — если создать не удалось.
     */
    private function prepareHome(): ?string
    {
        $home = $this->homeDir !== '' ? rtrim($this->homeDir, '/\\') : sys_get_temp_dir() . '/headless';

        if (!is_dir($home) && !@mkdir($home, 0775, true) && !is_dir($home)) {
            Log::warning('HeadlessPageRenderer: home dir not writable', ['home' => $home]);

            return null;
        }

        return $home;
    }
}
