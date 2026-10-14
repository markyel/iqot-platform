<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Monitoring\MonitoringGovernor;
use App\Support\Monitoring\MonitoringSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Мониторинг цен: агрессивность — глубина поиска и суточные бюджеты (с какой скоростью
 * проглатываем позиции и сколько тратим). Значения в system_settings, действуют сразу.
 */
class MonitoringSettingsController extends Controller
{
    public function index(MonitoringGovernor $governor)
    {
        $values = MonitoringSettings::all();
        $states = DB::table('mp_products')->selectRaw('search_state, COUNT(*) c')->groupBy('search_state')->pluck('c', 'search_state')->all();

        return view('admin.monitoring.settings', [
            'values' => $values,
            'preset' => MonitoringSettings::preset(),
            'presets' => MonitoringSettings::PRESETS,
            'estimate' => MonitoringSettings::estimate($values),
            'today' => $governor->today(),
            'backlog' => (int) ($states['new'] ?? 0) + (int) ($states['queued'] ?? 0),
            'searchStates' => $states,
            'enabled' => (bool) config('monitoring.enabled'),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'preset' => 'required|string|in:custom,' . implode(',', array_keys(MonitoringSettings::PRESETS)),
            'search_queries' => 'required|integer|min:1|max:12',
            'search_candidates' => 'required|integer|min:1|max:50',
            'yandex_per_day' => 'required|integer|min:0|max:200000',
            'fetch_per_day' => 'required|integer|min:0|max:500000',
            'headless_per_day' => 'required|integer|min:0|max:50000',
            'llm_usd_per_day' => 'required|numeric|min:0|max:1000',
        ]);
        $preset = $data['preset'];
        $values = $preset !== 'custom' ? array_intersect_key(MonitoringSettings::PRESETS[$preset], MonitoringSettings::FIELDS) : $data;
        MonitoringSettings::save($values, $preset);

        return redirect()->route('admin.monitoring.settings')
            ->with('success', 'Агрессивность мониторинга сохранена: ' . ($preset === 'custom' ? 'свои значения' : MonitoringSettings::PRESETS[$preset]['label']) . '. Действует сразу.');
    }
}
