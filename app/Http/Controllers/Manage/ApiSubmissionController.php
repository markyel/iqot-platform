<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Api\ApiSubmission;
use App\Models\Api\RequestItemStaging;
use App\Models\ApplicationDomain;
use App\Models\ProductType;
use App\Services\Api\ModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApiSubmissionController extends Controller
{
    public function __construct(private readonly ModerationService $moderation)
    {
    }

    public function index(Request $request): View
    {
        // Стадии, означающие «требует действий модератора».
        $pendingStages = ['inbox_buffered', 'classifying', 'awaiting_moderation', 'in_moderation'];

        $filter = $request->query('filter', 'pending');
        if (!in_array($filter, ['pending', 'ready', 'cancelled', 'all'], true)) {
            $filter = 'pending';
        }

        // Счётчики для вкладок (по всем submission с staging).
        $base = ApiSubmission::query()->whereHas('staging');
        $counts = [
            'pending' => (clone $base)->whereIn('stage', $pendingStages)
                ->where('status', '!=', 'cancelled')->count(),
            'ready' => (clone $base)->where('status', 'ready')->count(),
            'cancelled' => (clone $base)->where('status', 'cancelled')->count(),
            'all' => (clone $base)->count(),
        ];

        $query = ApiSubmission::query()
            ->with(['client.user', 'staging.items'])
            ->whereHas('staging')
            ->orderByDesc('updated_at');

        switch ($filter) {
            case 'pending':
                $query->whereIn('stage', $pendingStages)->where('status', '!=', 'cancelled');
                break;
            case 'ready':
                $query->where('status', 'ready');
                break;
            case 'cancelled':
                $query->where('status', 'cancelled');
                break;
            case 'all':
                // без доп. фильтров
                break;
        }

        $submissions = $query->limit(200)->get();

        // Подсчёт бейджей по trust_level для каждого submission.
        $badges = [];
        foreach ($submissions as $s) {
            $items = $s->staging?->items ?? collect();
            $badges[$s->id] = [
                'total' => $items->count(),
                'green' => $items->where('item_status', 'classified')->where('trust_level', 'green')->count(),
                'yellow' => $items->where('item_status', 'classified')->where('trust_level', 'yellow')->count(),
                'red' => $items->where('item_status', 'classified')->where('trust_level', 'red')->count(),
                'accepted' => $items->where('item_status', 'accepted')->count(),
                'rejected' => $items->where('item_status', 'rejected')->count(),
            ];
        }

        return view('admin.api-submissions.index', [
            'submissions' => $submissions,
            'badges' => $badges,
            'filter' => $filter,
            'counts' => $counts,
        ]);
    }

    public function show(ApiSubmission $submission): View
    {
        $submission->load(['client.user', 'sender', 'staging.items.clientCategory']);

        $items = $submission->staging?->items ?? collect();

        $productTypeIds = $items->pluck('product_type_id')->filter()->unique()->all();
        $domainIds = $items->pluck('domain_id')->filter()->unique()->all();

        $productTypes = ProductType::whereIn('id', $productTypeIds)->get()->keyBy('id');
        $domains = ApplicationDomain::whereIn('id', $domainIds)->get()->keyBy('id');

        // Справочники для datalist в reclassify-форме: активные leaf product_types + все активные domains.
        $productTypesAll = ProductType::query()
            ->where('is_active', 1)
            ->where('status', 'active')
            ->where('is_leaf', 1)
            ->orderBy('name')
            ->get(['id', 'slug', 'name']);

        $domainsAll = ApplicationDomain::query()
            ->where('is_active', 1)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'slug', 'name']);

        return view('admin.api-submissions.show', [
            'submission' => $submission,
            'items' => $items,
            'productTypes' => $productTypes,
            'domains' => $domains,
            'productTypesAll' => $productTypesAll,
            'domainsAll' => $domainsAll,
            'rejectReasons' => ModerationService::REJECT_REASONS,
        ]);
    }

    public function approveBatchGreen(ApiSubmission $submission): RedirectResponse
    {
        $n = $this->moderation->approveGreenBatch($submission);
        return redirect()
            ->route('admin.api-submissions.show', $submission)
            ->with('success', "Одобрено green-позиций: {$n}.");
    }

    public function approveItem(ApiSubmission $submission, RequestItemStaging $item): RedirectResponse
    {
        $this->assertBelongs($submission, $item);
        $this->moderation->approveItem($item);
        return back()->with('success', 'Позиция одобрена.');
    }

    public function rejectItem(
        Request $request,
        ApiSubmission $submission,
        RequestItemStaging $item,
    ): RedirectResponse {
        $this->assertBelongs($submission, $item);
        $data = $request->validate([
            'reason' => 'required|string|in:' . implode(',', array_keys(ModerationService::REJECT_REASONS)),
            'message' => 'nullable|string|max:2000',
        ]);
        $this->moderation->rejectItem($item, $data['reason'], $data['message'] ?? null);
        return back()->with('success', 'Позиция отклонена.');
    }

    public function reclassifyItem(
        Request $request,
        ApiSubmission $submission,
        RequestItemStaging $item,
    ): RedirectResponse {
        $this->assertBelongs($submission, $item);
        $data = $request->validate([
            'product_type_id' => 'nullable|integer|min:1',
            'domain_id' => 'nullable|integer|min:1',
        ]);
        $this->moderation->reclassifyItem(
            $item,
            isset($data['product_type_id']) ? (int) $data['product_type_id'] : null,
            isset($data['domain_id']) ? (int) $data['domain_id'] : null,
        );
        return back()->with('success', 'Классификация обновлена.');
    }

    private function assertBelongs(ApiSubmission $submission, RequestItemStaging $item): void
    {
        $stagingId = $submission->staging?->id;
        if (!$stagingId || $item->request_staging_id !== $stagingId) {
            abort(404);
        }
    }
}
