<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApplicationDomain;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CatalogExportController extends Controller
{
    /**
     * Проверка доступности API
     * GET /api/v1/health
     */
    public function health(): JsonResponse
    {
        $sourceUpdatedAt = DB::connection('reports')
            ->table('categories')
            ->max('updated_at') ?? now();

        return $this->success([
            'status' => 'ok',
            'version' => '2.0',
            'source_updated_at' => $sourceUpdatedAt,
        ]);
    }

    /**
     * Получить все домены
     * GET /api/v1/domains
     */
    public function domains(): JsonResponse
    {
        $domains = ApplicationDomain::where('is_active', true)
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->get()
            ->map(function ($domain) {
                return $this->formatDomain($domain, true);
            });

        return $this->success($domains, [
            'total' => $domains->count(),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Получить один домен
     * GET /api/v1/domains/{id}
     */
    public function showDomain(string $id): JsonResponse
    {
        $domain = ApplicationDomain::where('slug', $id)
            ->where('is_active', true)
            ->where('status', 'active')
            ->first();

        if (!$domain) {
            return $this->error('NOT_FOUND', 'Domain not found', 404);
        }

        $categoriesCount = Category::where('is_active', true)->count();

        return $this->success($this->formatDomain($domain, false, $categoriesCount));
    }

    /**
     * Получить все категории (плоский список)
     * GET /api/v1/categories?domain={slug}
     */
    public function categories(Request $request): JsonResponse
    {
        $query = Category::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($domainSlug = $request->query('domain')) {
            $domain = ApplicationDomain::where('slug', $domainSlug)->first();
            if (!$domain) {
                return $this->error('NOT_FOUND', 'Domain not found', 404);
            }
            // Если у категорий есть связь с доменами, фильтруем
            // Пока оставляем без фильтрации, так как в текущей структуре нет domain_id
        }

        $categories = $query->get()->map(function ($category) {
            return $this->formatCategory($category);
        });

        return $this->success($categories, [
            'total' => $categories->count(),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Получить дерево категорий
     * GET /api/v1/categories/tree?domain={slug}&max_depth={int}
     */
    public function categoriesTree(Request $request): JsonResponse
    {
        $domainSlug = $request->query('domain');
        $maxDepth = min((int) $request->query('max_depth', 10), 10);

        if (!$domainSlug) {
            return $this->error('BAD_REQUEST', 'Domain parameter is required', 400);
        }

        $domain = ApplicationDomain::where('slug', $domainSlug)
            ->where('is_active', true)
            ->first();

        if (!$domain) {
            return $this->error('NOT_FOUND', 'Domain not found', 404);
        }

        // Получаем все активные категории
        $categories = Category::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Строим дерево
        $tree = $this->buildTree($categories, null, 0, $maxDepth);

        return $this->success($tree, [
            'domain' => $domainSlug,
            'max_depth' => $maxDepth,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Получить одну категорию
     * GET /api/v1/categories/{id}
     */
    public function showCategory(string $id): JsonResponse
    {
        $category = Category::where('slug', $id)
            ->orWhere('id', $id)
            ->where('is_active', true)
            ->first();

        if (!$category) {
            return $this->error('NOT_FOUND', 'Category not found', 404);
        }

        $data = $this->formatCategory($category);

        // Добавляем breadcrumbs и children
        $data['breadcrumbs'] = $this->getBreadcrumbs($category);
        $data['children'] = Category::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn($c) => ['id' => $c->slug ?? $c->id, 'name' => $c->name])
            ->values();

        return $this->success($data);
    }

    /**
     * Полный экспорт каталога
     * GET /api/v1/export
     */
    public function export(): JsonResponse
    {
        $domains = ApplicationDomain::where('is_active', true)
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->get()
            ->map(fn($d) => $this->formatDomain($d, false));

        $categories = Category::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn($c) => $this->formatCategory($c));

        $data = [
            'domains' => $domains,
            'categories' => $categories,
        ];

        $checksum = 'sha256:' . hash('sha256', json_encode($data));

        return $this->success($data, [
            'domains_count' => $domains->count(),
            'categories_count' => $categories->count(),
            'generated_at' => now()->toIso8601String(),
            'checksum' => $checksum,
        ]);
    }

    /**
     * Форматирование домена для API
     */
    private function formatDomain(ApplicationDomain $domain, bool $withCounts = false, ?int $categoriesCount = null): array
    {
        $data = [
            'id' => $domain->slug,
            'name' => $domain->name,
            'name_en' => null, // Добавьте поле в модель если нужно
            'description' => $domain->description,
            'icon' => null, // Добавьте поле в модель если нужно
            'sort_order' => $domain->sort_order,
        ];

        if ($withCounts || $categoriesCount !== null) {
            $data['categories_count'] = $categoriesCount ?? Category::where('is_active', true)->count();
        }

        // SEO данные если есть
        if (!$withCounts) {
            $data['seo'] = [
                'title' => null,
                'description' => $domain->description,
                'keywords' => $domain->keywords ?? [],
            ];
        }

        return $data;
    }

    /**
     * Форматирование категории для API
     */
    private function formatCategory(Category $category): array
    {
        return [
            'id' => $category->slug ?? (string) $category->id,
            'domain_id' => 'lifty', // По умолчанию, измените если есть связь
            'parent_id' => null, // Добавьте parent_id в модель если есть иерархия
            'name' => $category->name,
            'name_en' => null, // Добавьте поле в модель если нужно
            'description' => $category->description,
            'image' => null, // Добавьте поле в модель если нужно
            'icon' => null, // Добавьте поле в модель если нужно
            'sort_order' => $category->sort_order,
            'depth' => 0, // Рассчитывается на основе parent_id
            'seo' => [
                'title' => null,
                'description' => $category->description,
                'keywords' => [],
            ],
        ];
    }

    /**
     * Построить дерево категорий
     */
    private function buildTree($categories, $parentId, $depth, $maxDepth): array
    {
        if ($depth >= $maxDepth) {
            return [];
        }

        return $categories
            ->filter(fn($c) => ($c->parent_id ?? null) == $parentId)
            ->map(function ($category) use ($categories, $depth, $maxDepth) {
                return [
                    'id' => $category->slug ?? $category->id,
                    'name' => $category->name,
                    'icon' => null,
                    'sort_order' => $category->sort_order,
                    'children' => $this->buildTree($categories, $category->id, $depth + 1, $maxDepth),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Получить breadcrumbs для категории
     */
    private function getBreadcrumbs(Category $category): array
    {
        // Упрощенная версия без parent_id
        return [
            ['id' => 'lifty', 'name' => 'Лифты', 'type' => 'domain'],
        ];
    }

    /**
     * Успешный ответ
     */
    private function success($data, array $meta = []): JsonResponse
    {
        $response = [
            'success' => true,
            'data' => $data,
        ];

        if (!empty($meta)) {
            $response['meta'] = array_merge([
                'generated_at' => now()->toIso8601String(),
                'version' => '2.0',
            ], $meta);
        }

        return response()->json($response);
    }

    /**
     * Ответ с ошибкой
     */
    private function error(string $code, string $message, int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }
}
