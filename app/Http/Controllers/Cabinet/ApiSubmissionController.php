<?php

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Models\Api\ApiClient;
use App\Models\Api\ApiSubmission;
use App\Models\Api\RequestItemStaging;
use App\Models\ApplicationDomain;
use App\Models\ExternalOffer;
use App\Models\ExternalRequestItem;
use App\Models\ProductType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ApiSubmissionController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $user = Auth::user();
        $client = ApiClient::query()->where('user_id', $user->id)->first();
        if (!$client) {
            return view('cabinet.api_submissions.index', [
                'submissions' => collect(),
                'itemsCount' => [],
                'offersCount' => [],
            ]);
        }

        $submissions = ApiSubmission::query()
            ->where('api_client_id', $client->id)
            ->with('sender')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        // Подсчёт позиций и офферов для каждой submission (cross-DB).
        $itemsCount = [];
        $offersCount = [];
        $internalIds = $submissions->pluck('internal_request_id')->filter()->all();
        $stagingCountBySub = RequestItemStaging::query()
            ->whereHas('staging', fn ($q) => $q->whereIn('api_submission_id', $submissions->pluck('id')))
            ->get(['id', 'request_staging_id'])
            ->load('staging:id,api_submission_id')
            ->groupBy(fn ($i) => $i->staging?->api_submission_id)
            ->map->count()
            ->all();

        $reportsItemsBySub = ExternalRequestItem::query()
            ->whereIn('request_id', $internalIds)
            ->get(['id', 'request_id']);
        $reportsItemsCount = $reportsItemsBySub->groupBy('request_id')->map->count()->all();

        $offerCounts = !empty($internalIds)
            ? ExternalOffer::query()
                ->whereIn('request_item_id', $reportsItemsBySub->pluck('id'))
                ->whereIn('status', ['received', 'processed'])
                ->selectRaw('request_item_id, COUNT(*) as c')
                ->groupBy('request_item_id')
                ->pluck('c', 'request_item_id')
                ->all()
            : [];

        foreach ($submissions as $s) {
            $itemsCount[$s->id] = $s->internal_request_id
                ? ($reportsItemsCount[$s->internal_request_id] ?? $s->items_total)
                : ($stagingCountBySub[$s->id] ?? $s->items_total);

            $total = 0;
            if ($s->internal_request_id) {
                foreach ($reportsItemsBySub->where('request_id', $s->internal_request_id) as $ri) {
                    $total += (int) ($offerCounts[$ri->id] ?? 0);
                }
            }
            $offersCount[$s->id] = $total;
        }

        return view('cabinet.api_submissions.index', [
            'submissions' => $submissions,
            'itemsCount' => $itemsCount,
            'offersCount' => $offersCount,
        ]);
    }

    public function show(Request $request, ApiSubmission $submission): View|RedirectResponse
    {
        $user = Auth::user();
        $client = ApiClient::query()->where('user_id', $user->id)->first();
        if (!$client || $submission->api_client_id !== $client->id) {
            abort(404);
        }

        $submission->load('sender', 'staging.items');

        $items = collect();
        $productTypes = collect();
        $domains = collect();
        $offerCounts = [];

        if ($submission->internal_request_id) {
            $items = ExternalRequestItem::query()
                ->where('request_id', $submission->internal_request_id)
                ->orderBy('position_number')
                ->get();
            $productTypes = ProductType::whereIn('id', $items->pluck('product_type_id')->filter())->get()->keyBy('id');
            $domains = ApplicationDomain::whereIn('id', $items->pluck('domain_id')->filter())->get()->keyBy('id');
            $offerCounts = ExternalOffer::query()
                ->whereIn('request_item_id', $items->pluck('id'))
                ->whereIn('status', ['received', 'processed'])
                ->selectRaw('request_item_id, COUNT(*) as c')
                ->groupBy('request_item_id')
                ->pluck('c', 'request_item_id')
                ->all();
        } else {
            $items = $submission->staging?->items ?? collect();
            $productTypes = ProductType::whereIn('id', $items->pluck('product_type_id')->filter())->get()->keyBy('id');
            $domains = ApplicationDomain::whereIn('id', $items->pluck('domain_id')->filter())->get()->keyBy('id');
        }

        return view('cabinet.api_submissions.show', [
            'submission' => $submission,
            'items' => $items,
            'isPromoted' => $submission->internal_request_id !== null,
            'productTypes' => $productTypes,
            'domains' => $domains,
            'offerCounts' => $offerCounts,
        ]);
    }
}
