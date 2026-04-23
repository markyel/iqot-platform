<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Api\ApiClient;
use App\Models\Api\ApiSubmission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Управление API-клиентами с админской стороны.
 *
 * Сейчас умеет:
 *  - список api_clients с привязанными пользователями + сводкой submissions
 *  - переключение флагов клиента (is_active, auto_approve_green)
 */
class ApiClientController extends Controller
{
    public function index(): View
    {
        $clients = ApiClient::query()->orderBy('id')->get();
        $userIds = $clients->pluck('user_id')->unique()->all();
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        // Сводка по submissions для каждого клиента.
        $submissionStats = ApiSubmission::query()
            ->selectRaw('api_client_id, COUNT(*) as total, SUM(items_total) as items_total')
            ->whereIn('api_client_id', $clients->pluck('id'))
            ->groupBy('api_client_id')
            ->get()
            ->keyBy('api_client_id');

        return view('admin.api-clients.index', [
            'clients' => $clients,
            'users' => $users,
            'submissionStats' => $submissionStats,
        ]);
    }

    public function update(Request $request, ApiClient $client): RedirectResponse
    {
        $client->update([
            'is_active' => $request->boolean('is_active'),
            'auto_approve_green' => $request->boolean('auto_approve_green'),
        ]);

        return redirect()->route('admin.api-clients.index')
            ->with('success', 'Настройки клиента #' . $client->id . ' сохранены.');
    }
}
