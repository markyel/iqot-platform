<?php

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;

/**
 * Документация публичного API в кабинете: руководство (docs/api/README.md), интерактивный
 * справочник методов (Redoc по openapi.yaml) и скачивание спецификации. Источник — файлы
 * репозитория, поэтому документация совпадает с выкаченной версией API.
 */
class ApiDocsController extends Controller
{
    public function guide()
    {
        $md = (string) @file_get_contents(base_path('docs/api/README.md'));
        $html = Str::markdown($md, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        // Ссылку на openapi.yaml из README ведём на маршрут скачивания.
        $html = str_replace('href="./openapi.yaml"', 'href="' . route('cabinet.api-docs.openapi') . '"', $html);

        return view('cabinet.api_docs.guide', ['html' => $html]);
    }

    public function reference()
    {
        return view('cabinet.api_docs.reference');
    }

    public function openapi()
    {
        return response()->file(base_path('docs/api/openapi.yaml'), [
            'Content-Type' => 'application/yaml; charset=utf-8',
            'Content-Disposition' => 'inline; filename="iqot-openapi.yaml"',
        ]);
    }
}
