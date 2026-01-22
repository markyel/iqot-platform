<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductType;
use App\Models\ApplicationDomain;
use App\Models\ExternalRequestItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TaxonomyController extends Controller
{
    /**
     * Список ожидающих модерации
     */
    public function pending(): JsonResponse
    {
        // Оптимизация: считаем items_count одним запросом
        $domainItemsCounts = \DB::connection('reports')
            ->table('request_items')
            ->select('domain_id', \DB::raw('COUNT(*) as count'))
            ->groupBy('domain_id')
            ->pluck('count', 'domain_id');

        $typeItemsCounts = \DB::connection('reports')
            ->table('request_items')
            ->select('product_type_id', \DB::raw('COUNT(*) as count'))
            ->groupBy('product_type_id')
            ->pluck('count', 'product_type_id');

        $pendingDomains = ApplicationDomain::pending()
            ->aiGenerated()
            ->with(['parent'])
            ->get()
            ->map(function ($domain) use ($domainItemsCounts) {
                return [
                    'id' => $domain->id,
                    'name' => $domain->name,
                    'slug' => $domain->slug,
                    'description' => $domain->description,
                    'keywords' => $domain->keywords,
                    'parent_id' => $domain->parent_id,
                    'parent_name' => $domain->parent?->name,
                    'created_by' => $domain->created_by,
                    'created_at' => $domain->created_at,
                    'items_count' => $domainItemsCounts[$domain->id] ?? 0,
                ];
            });

        $pendingTypes = ProductType::pending()
            ->aiGenerated()
            ->with(['parent'])
            ->get()
            ->map(function ($type) use ($typeItemsCounts) {
                return [
                    'id' => $type->id,
                    'name' => $type->name,
                    'slug' => $type->slug,
                    'description' => $type->description,
                    'keywords' => $type->keywords,
                    'parent_id' => $type->parent_id,
                    'parent_name' => $type->parent?->name,
                    'is_leaf' => $type->is_leaf,
                    'created_by' => $type->created_by,
                    'created_at' => $type->created_at,
                    'items_count' => $typeItemsCounts[$type->id] ?? 0,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'domains' => $pendingDomains,
                'product_types' => $pendingTypes,
            ],
            'meta' => [
                'total_pending' => $pendingDomains->count() + $pendingTypes->count(),
                'domains_pending' => $pendingDomains->count(),
                'types_pending' => $pendingTypes->count(),
            ]
        ]);
    }

    /**
     * Список доменов
     */
    public function domains(Request $request): JsonResponse
    {
        $query = ApplicationDomain::query();

        // Фильтры
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('created_by') && $request->created_by !== 'all') {
            $query->where('created_by', $request->created_by);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhereRaw("JSON_SEARCH(keywords, 'one', ?) IS NOT NULL", ['%' . $request->search . '%']);
            });
        }

        // Сортировка
        $sortField = $request->get('sort', 'sort_order');
        $sortOrder = $request->get('order', 'asc');
        $query->orderBy($sortField, $sortOrder);

        // Оптимизация: считаем items_count одним запросом
        $itemsCounts = \DB::connection('reports')
            ->table('request_items')
            ->select('domain_id', \DB::raw('COUNT(*) as count'))
            ->groupBy('domain_id')
            ->pluck('count', 'domain_id');

        $domains = $query->get()->map(function ($domain) use ($itemsCounts) {
            return [
                'id' => $domain->id,
                'slug' => $domain->slug,
                'name' => $domain->name,
                'description' => $domain->description,
                'keywords' => $domain->keywords,
                'parent_id' => $domain->parent_id,
                'is_active' => $domain->is_active,
                'is_verified' => $domain->is_verified,
                'status' => $domain->status,
                'source' => $domain->source,
                'sort_order' => $domain->sort_order,
                'created_at' => $domain->created_at,
                'updated_at' => $domain->updated_at,
                'stats' => [
                    'items_count' => $itemsCounts[$domain->id] ?? 0,
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $domains,
            'meta' => [
                'total' => $domains->count(),
                'pending_count' => ApplicationDomain::pending()->count(),
            ]
        ]);
    }

    /**
     * Получить домен
     */
    public function showDomain($id): JsonResponse
    {
        $domain = ApplicationDomain::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $domain->id,
                'slug' => $domain->slug,
                'name' => $domain->name,
                'description' => $domain->description,
                'keywords' => $domain->keywords,
                'parent_id' => $domain->parent_id,
                'is_active' => $domain->is_active,
                'is_verified' => $domain->is_verified,
                'status' => $domain->status,
                'source' => $domain->source,
                'sort_order' => $domain->sort_order,
                'created_at' => $domain->created_at,
                'updated_at' => $domain->updated_at,
                'stats' => [
                    'items_count' => ExternalRequestItem::where('domain_id', $domain->id)->count(),
                ],
                'recent_items' => ExternalRequestItem::where('domain_id', $domain->id)
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get(['id', 'name', 'created_at']),
            ]
        ]);
    }

    /**
     * Одобрить домен
     */
    public function approveDomain(Request $request, $id): JsonResponse
    {
        $domain = ApplicationDomain::findOrFail($id);

        $updates = $request->only(['name', 'slug', 'description', 'keywords', 'sort_order']);

        $domain->approve($updates);

        return response()->json([
            'success' => true,
            'message' => 'Домен одобрен',
            'data' => $domain->fresh()
        ]);
    }

    /**
     * Отклонить домен
     */
    public function rejectDomain(Request $request, $id): JsonResponse
    {
        $domain = ApplicationDomain::findOrFail($id);

        // Если указан merge_into - переносим items
        if ($request->filled('merge_into')) {
            $targetDomain = ApplicationDomain::findOrFail($request->merge_into);
            ExternalRequestItem::where('domain_id', $id)
                ->update(['domain_id' => $targetDomain->id]);
        }

        $domain->reject();

        return response()->json([
            'success' => true,
            'message' => 'Домен отклонен'
        ]);
    }

    /**
     * Список типов товаров (плоский)
     */
    public function productTypes(Request $request): JsonResponse
    {
        $query = ProductType::query();

        // Фильтры
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('created_by') && $request->created_by !== 'all') {
            $query->where('created_by', $request->created_by);
        }

        if ($request->filled('is_leaf')) {
            $query->where('is_leaf', $request->boolean('is_leaf'));
        }

        if ($request->filled('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhereRaw("JSON_SEARCH(keywords, 'one', ?) IS NOT NULL", ['%' . $request->search . '%']);
            });
        }

        // Сортировка
        $sortField = $request->get('sort', 'sort_order');
        $sortOrder = $request->get('order', 'asc');
        $query->orderBy($sortField, $sortOrder);

        // Оптимизация: считаем items_count одним запросом
        $itemsCounts = \DB::connection('reports')
            ->table('request_items')
            ->select('product_type_id', \DB::raw('COUNT(*) as count'))
            ->groupBy('product_type_id')
            ->pluck('count', 'product_type_id');

        $types = $query->with('parent')->get()->map(function ($type) use ($itemsCounts) {
            return [
                'id' => $type->id,
                'slug' => $type->slug,
                'name' => $type->name,
                'description' => $type->description,
                'keywords' => $type->keywords,
                'parent_id' => $type->parent_id,
                'parent_name' => $type->parent?->name,
                'is_leaf' => $type->is_leaf,
                'is_active' => $type->is_active,
                'is_verified' => $type->is_verified,
                'status' => $type->status,
                'source' => $type->source,
                'sort_order' => $type->sort_order,
                'created_at' => $type->created_at,
                'updated_at' => $type->updated_at,
                'stats' => [
                    'items_count' => $itemsCounts[$type->id] ?? 0,
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $types,
            'meta' => [
                'total' => $types->count(),
                'pending_count' => ProductType::pending()->count(),
            ]
        ]);
    }

    /**
     * Получить тип товара
     */
    public function showProductType($id): JsonResponse
    {
        $type = ProductType::with('parent')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $type->id,
                'slug' => $type->slug,
                'name' => $type->name,
                'description' => $type->description,
                'keywords' => $type->keywords,
                'parent_id' => $type->parent_id,
                'parent' => $type->parent ? [
                    'id' => $type->parent->id,
                    'name' => $type->parent->name,
                ] : null,
                'is_leaf' => $type->is_leaf,
                'is_active' => $type->is_active,
                'is_verified' => $type->is_verified,
                'status' => $type->status,
                'source' => $type->source,
                'sort_order' => $type->sort_order,
                'created_at' => $type->created_at,
                'updated_at' => $type->updated_at,
                'stats' => [
                    'items_count' => ExternalRequestItem::where('product_type_id', $type->id)->count(),
                ],
                'recent_items' => ExternalRequestItem::where('product_type_id', $type->id)
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get(['id', 'name', 'created_at']),
            ]
        ]);
    }

    /**
     * Одобрить тип товара
     */
    public function approveProductType(Request $request, $id): JsonResponse
    {
        $type = ProductType::findOrFail($id);

        $updates = $request->only(['name', 'slug', 'description', 'keywords', 'parent_id', 'sort_order']);

        $type->approve($updates);

        return response()->json([
            'success' => true,
            'message' => 'Тип товара одобрен',
            'data' => $type->fresh()
        ]);
    }

    /**
     * Отклонить тип товара
     */
    public function rejectProductType(Request $request, $id): JsonResponse
    {
        $type = ProductType::findOrFail($id);

        // Если указан merge_into - переносим items
        if ($request->filled('merge_into')) {
            $targetType = ProductType::findOrFail($request->merge_into);
            ExternalRequestItem::where('product_type_id', $id)
                ->update(['product_type_id' => $targetType->id]);
        }

        $type->reject();

        return response()->json([
            'success' => true,
            'message' => 'Тип товара отклонен'
        ]);
    }

    /**
     * Обновить домен
     */
    public function updateDomain(Request $request, $id): JsonResponse
    {
        $domain = ApplicationDomain::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'keywords' => 'nullable|array',
            'parent_id' => 'nullable|integer|exists:application_domains,id',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);

        $domain->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Домен обновлен',
            'data' => $domain->fresh()
        ]);
    }

    /**
     * Обновить тип товара
     */
    public function updateProductType(Request $request, $id): JsonResponse
    {
        $type = ProductType::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'keywords' => 'nullable|array',
            'parent_id' => 'nullable|integer|exists:product_types,id',
            'is_active' => 'sometimes|boolean',
            'is_leaf' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);

        $type->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Тип товара обновлен',
            'data' => $type->fresh()
        ]);
    }

    /**
     * Статистика таксономии
     */
    public function stats(): JsonResponse
    {
        $totalItems = ExternalRequestItem::count();
        $classifiedItems = ExternalRequestItem::whereNotNull('product_type_id')
            ->orWhereNotNull('domain_id')
            ->count();

        $pendingDomains = ApplicationDomain::pending()->count();
        $pendingTypes = ProductType::pending()->count();
        $totalDomains = ApplicationDomain::count();
        $totalTypes = ProductType::count();

        return response()->json([
            'success' => true,
            'data' => [
                'pending_count' => $pendingDomains + $pendingTypes,
                'total_domains' => $totalDomains,
                'total_types' => $totalTypes,
                'domains' => [
                    'total' => $totalDomains,
                    'active' => ApplicationDomain::where('status', 'active')->count(),
                    'pending' => $pendingDomains,
                    'ai_generated' => ApplicationDomain::aiGenerated()->count(),
                ],
                'product_types' => [
                    'total' => $totalTypes,
                    'active' => ProductType::where('status', 'active')->count(),
                    'pending' => $pendingTypes,
                    'leaf_count' => ProductType::where('is_leaf', true)->count(),
                    'group_count' => ProductType::where('is_leaf', false)->count(),
                    'ai_generated' => ProductType::aiGenerated()->count(),
                ],
                'coverage' => [
                    'items_total' => $totalItems,
                    'items_classified' => $classifiedItems,
                    'items_unclassified' => $totalItems - $classifiedItems,
                    'classification_rate' => $totalItems > 0 ? round($classifiedItems / $totalItems, 3) : 0,
                ]
            ]
        ]);
    }
}
