<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Analysis\HeadlessPageRenderer;
use Illuminate\Http\Request;

/**
 * Админ-инструмент внешнего теста headless-рендера: ввёл URL → отдаётся PNG-скриншот
 * страницы (и опционально извлечённый видимый текст — ровно то, что видит AI при
 * парсинге цен). Использует тот же HeadlessPageRenderer/Browsershot, что и боевой
 * шаг emails:analyze-replies, поэтому проверяет реальную среду рендера на проде.
 */
class HeadlessTestController extends Controller
{
    public function index()
    {
        return view('admin.tools.headless-test', [
            'result' => null,
            'url' => '',
            'fullPage' => true,
            'withText' => true,
        ]);
    }

    public function render(Request $request)
    {
        $validated = $request->validate([
            'url' => ['required', 'string', 'max:2000'],
        ]);

        $url = trim($validated['url']);
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url; // без схемы → https
        }

        $fullPage = $request->boolean('full_page');
        $withText = $request->boolean('with_text');

        $renderer = HeadlessPageRenderer::fromConfig();

        $result = [
            'ok' => false,
            'error' => null,
            'png_base64' => null,
            'text' => null,
            'text_length' => 0,
            'elapsed_ms' => 0,
            'chrome' => config('services.email_analysis.headless_chrome_path'),
        ];

        $start = microtime(true);

        $png = $renderer->screenshot($url, $fullPage);
        if ($png !== null) {
            $result['ok'] = true;
            $result['png_base64'] = base64_encode($png);

            if ($withText) {
                $text = $renderer->render($url);
                $result['text'] = $text;
                $result['text_length'] = $text !== null ? mb_strlen($text) : 0;
            }
        } else {
            $result['error'] = 'Рендер не удался. Вероятные причины: недоступный URL, таймаут, '
                . 'JS-заглушка/антибот, нехватка памяти под Chrome. Подробности — в laravel.log '
                . '(метка «HeadlessPageRenderer»).';
        }

        $result['elapsed_ms'] = (int) round((microtime(true) - $start) * 1000);

        return view('admin.tools.headless-test', [
            'result' => $result,
            'url' => $url,
            'fullPage' => $fullPage,
            'withText' => $withText,
        ]);
    }
}
