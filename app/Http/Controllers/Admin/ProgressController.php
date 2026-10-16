<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ProgressDashboard;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;

/**
 * Вкладки раздела «Ход выполнения» (/manage/emails/campaigns/...): карточка заявки, API-заявки,
 * мониторинг цен, охват поставщиков, ресурсы системы. Первая вкладка «Заявки» —
 * EmailCampaignStatsController. Тяжёлые агрегаты кешируются (страницы наблюдательные).
 */
class ProgressController extends Controller
{
    public function __construct(private readonly ProgressDashboard $dash)
    {
    }

    public function request(int $id): View
    {
        $data = $this->safe(fn () => Cache::remember("admin:progress:request:{$id}", 60, fn () => $this->dash->request($id)));
        abort_if(($data['ok'] ?? false) && $data['value'] === null, 404);

        return view('admin.progress.request', ['data' => $data]);
    }

    public function api(): View
    {
        return view('admin.progress.api', ['data' => $this->safe(fn () => Cache::remember('admin:progress:api', 60, fn () => $this->dash->api()))]);
    }

    public function monitoring(): View
    {
        return view('admin.progress.monitoring', ['data' => $this->safe(fn () => Cache::remember('admin:progress:monitoring', 60, fn () => $this->dash->monitoring()))]);
    }

    public function coverage(): View
    {
        // Месячный агрегат по большой email_queue — 10 минут кеша.
        return view('admin.progress.coverage', ['data' => $this->safe(fn () => Cache::remember('admin:progress:coverage', 600, fn () => $this->dash->coverage()))]);
    }

    public function resources(): View
    {
        // Живые пробы релеев и ИИ-прокси — короткий кеш.
        return view('admin.progress.resources', ['data' => $this->safe(fn () => Cache::remember('admin:progress:resources', 30, fn () => $this->dash->resources()))]);
    }

    public function alias(): View
    {
        return view('admin.progress.alias', ['data' => $this->safe(fn () => Cache::remember('admin:progress:alias', 60, fn () => $this->dash->aliasExp()))]);
    }

    /** @return array{ok: bool, value?: mixed, error?: string} */
    private function safe(callable $fn): array
    {
        try {
            return ['ok' => true, 'value' => $fn()];
        } catch (\Throwable $e) {
            report($e);

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
