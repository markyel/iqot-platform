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
 * вызывающий код продолжает на том, что есть.
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
    ) {
    }

    public function render(string $url): ?string
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

        try {
            $text = Browsershot::url($url)
                ->setChromePath($this->chromePath)
                ->noSandbox()
                ->setOption('args', [
                    '--disable-dev-shm-usage',
                    '--disable-gpu',
                    '--user-data-dir=' . $home . '/profile',
                    '--crash-dumps-dir=' . $home . '/crash',
                ])
                ->timeout($this->timeout)
                ->waitUntilNetworkIdle()
                ->evaluate('document.body.innerText');
        } catch (\Throwable $e) {
            Log::warning('HeadlessPageRenderer: render failed', [
                'url' => mb_substr($url, 0, 200),
                'error' => mb_substr($e->getMessage(), 0, 300),
            ]);

            return null;
        }

        if (!is_string($text)) {
            return null;
        }

        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);

        return $text === '' ? null : $text;
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
