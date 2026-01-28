<?php

namespace App\Http\Controllers;

use App\Models\ProductType;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Генерация sitemap.xml
     */
    public function index(): Response
    {
        $productTypes = ProductType::where('is_active', true)
            ->where('status', 'active')
            ->where('is_leaf', true)
            ->orderBy('updated_at', 'desc')
            ->get(['id', 'slug', 'updated_at']);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

        // Главная страница
        $xml .= $this->addUrl('/', now(), '1.0', 'daily');

        // Статичные информационные страницы
        $xml .= $this->addUrl('/faq', now(), '0.9', 'weekly');
        $xml .= $this->addUrl('/why-it-works', now(), '0.9', 'weekly');
        $xml .= $this->addUrl('/pricing', now(), '0.9', 'daily');
        $xml .= $this->addUrl('/terms', now(), '0.7', 'monthly');
        $xml .= $this->addUrl('/privacy', now(), '0.7', 'monthly');
        $xml .= $this->addUrl('/contract', now(), '0.7', 'monthly');

        // Каталог
        $xml .= $this->addUrl('/catalog', now(), '0.9', 'daily');

        // Категории каталога
        foreach ($productTypes as $type) {
            $url = '/catalog/' . $type->id;
            $xml .= $this->addUrl($url, $type->updated_at, '0.8', 'weekly');
        }

        $xml .= '</urlset>';

        return response($xml, 200)
            ->header('Content-Type', 'application/xml');
    }

    /**
     * Формирование URL элемента sitemap
     */
    private function addUrl(string $path, $lastmod, string $priority, string $changefreq): string
    {
        $url = url($path);
        $lastmodFormatted = $lastmod->format('Y-m-d');

        return <<<XML
  <url>
    <loc>{$url}</loc>
    <lastmod>{$lastmodFormatted}</lastmod>
    <changefreq>{$changefreq}</changefreq>
    <priority>{$priority}</priority>
  </url>

XML;
    }
}
