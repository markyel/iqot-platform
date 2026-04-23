<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Jobs\Api\DiscoverSuppliersForPairJob;
use App\Jobs\Api\DiscoveryOrchestratorJob;
use App\Models\Api\SupplierDiscoveryRun;
use App\Models\ApplicationDomain;
use App\Models\ExternalRequestItem;
use App\Models\ProductType;
use App\Services\Api\SupplierCoverageService;
use App\Services\Api\SupplierPoolService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SupplierDiscoveryController extends Controller
{
    public function index(Request $request): View
    {
        $statusFilter = $request->query('status');

        $query = SupplierDiscoveryRun::query()
            ->orderByDesc('id');
        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }
        $runs = $query->limit(100)->get();

        $productTypeIds = $runs->pluck('product_type_id')->unique()->all();
        $domainIds = $runs->pluck('domain_id')->filter()->unique()->all();
        $productTypes = ProductType::whereIn('id', $productTypeIds)->get()->keyBy('id');
        $domains = ApplicationDomain::whereIn('id', $domainIds)->get()->keyBy('id');

        $counts = SupplierDiscoveryRun::query()
            ->selectRaw('status, COUNT(*) AS c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();

        return view('admin.supplier-discovery.index', [
            'runs' => $runs,
            'productTypes' => $productTypes,
            'domains' => $domains,
            'counts' => $counts,
            'statusFilter' => $statusFilter,
        ]);
    }

    public function show(SupplierDiscoveryRun $run, SupplierCoverageService $coverage): View
    {
        $productType = ProductType::find($run->product_type_id);
        $domain = $run->domain_id ? ApplicationDomain::find($run->domain_id) : null;

        // Suppliers, созданные за время этого run (по created_at & profile_source='auto_refined').
        $createdSuppliers = collect();
        if ($run->started_at && $run->finished_at) {
            $createdSuppliers = DB::connection('reports')->table('suppliers')
                ->where('profile_source', 'auto_refined')
                ->whereBetween('created_at', [$run->started_at, $run->finished_at])
                ->whereIn('id', function ($q) use ($run) {
                    $q->from('supplier_product_types')
                        ->select('supplier_id')
                        ->where('product_type_id', $run->product_type_id);
                })
                ->orderBy('id')
                ->get(['id', 'name', 'email', 'phone', 'website', 'profile_confidence', 'created_at']);
        }

        // Текущее покрытие пары (domain, product_type) — считается по всем активным
        // поставщикам в БД, независимо от этого run.
        $coverageInfo = $coverage->checkCoverage($run->domain_id, $run->product_type_id);

        return view('admin.supplier-discovery.show', [
            'run' => $run,
            'productType' => $productType,
            'domain' => $domain,
            'createdSuppliers' => $createdSuppliers,
            'coverage' => $coverageInfo,
        ]);
    }

    /**
     * Ручной запуск сбора поставщиков по позиции заявки (ExternalRequestItem).
     */
    public function triggerForItem(Request $request, ExternalRequestItem $item): RedirectResponse
    {
        if ($item->product_type_id === null) {
            return back()->with('error', 'У позиции не проставлен product_type_id — невозможно запустить сбор.');
        }

        $pool = app(SupplierPoolService::class);
        $created = $pool->ensureDiscoveryRun(
            $item->domain_id,
            $item->product_type_id,
            null
        );

        if (!$created) {
            return back()->with(
                'warning',
                'Сбор не запущен: уже есть активный run на эту пару (domain/product_type) или активен cooldown. '
                . 'Дождитесь завершения текущего, либо истечения cooldown (см. supplier_discovery_runs).'
            );
        }

        // Попробуем запустить orchestrator синхронно для немедленной реакции.
        // В обычном режиме его поднимет scheduler every 10 min.
        app(\Illuminate\Contracts\Bus\Dispatcher::class)->dispatch(new DiscoveryOrchestratorJob());

        return back()->with('success', 'Сбор поставщиков запущен. Это может занять 5–15 минут.');
    }
}
