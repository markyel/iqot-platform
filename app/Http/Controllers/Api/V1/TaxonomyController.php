<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApplicationDomain;
use App\Models\ProductType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Справочники таксономии (§11.10).
 *
 * Публичный клиент читает только активные leaf-узлы; поля выдаются в
 * консистентной форме. Не используем внутренние поля (description, keywords).
 */
class TaxonomyController extends Controller
{
    public function domains(Request $request): JsonResponse
    {
        $items = ApplicationDomain::query()
            ->where('is_active', 1)
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'parent_id']);

        return $this->response($request, [
            'items' => $items->map(fn (ApplicationDomain $d) => [
                'id' => (int) $d->id,
                'slug' => $d->slug,
                'name' => $d->name,
                'parent_id' => $d->parent_id ? (int) $d->parent_id : null,
            ])->values()->all(),
        ]);
    }

    public function productTypes(Request $request): JsonResponse
    {
        $query = ProductType::query()
            ->where('is_active', 1)
            ->where('status', 'active')
            ->where('is_leaf', 1);

        // Фильтр по домену (через reports.domain_product_types).
        $domainId = $request->query('domain_id');
        if ($domainId !== null && ctype_digit((string) $domainId)) {
            $domainId = (int) $domainId;
            $query->whereIn('id', function ($sub) use ($domainId) {
                $sub->from('domain_product_types')
                    ->select('product_type_id')
                    ->where('domain_id', $domainId);
            });
        }

        $items = $query->orderBy('sort_order')->orderBy('name')
            ->limit(2000)
            ->get(['id', 'slug', 'name', 'parent_id']);

        return $this->response($request, [
            'items' => $items->map(fn (ProductType $p) => [
                'id' => (int) $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
                'parent_id' => $p->parent_id ? (int) $p->parent_id : null,
            ])->values()->all(),
            'filter' => $domainId !== null ? ['domain_id' => $domainId] : null,
            'count' => $items->count(),
        ]);
    }

    private function response(Request $request, array $payload): JsonResponse
    {
        $requestId = (string) $request->attributes->get('api_request_id');
        return response()->json($payload)->header('X-Request-Id', $requestId);
    }
}
