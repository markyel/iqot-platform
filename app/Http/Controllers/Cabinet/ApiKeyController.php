<?php

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Models\Api\ApiClient;
use App\Models\Api\ApiKey;
use App\Services\Api\ApiKeyService;
use App\Services\Api\UserAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ApiKeyController extends Controller
{
    public function __construct(
        private readonly ApiKeyService $apiKeyService,
        private readonly UserAccessService $userAccessService,
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $user = Auth::user();

        if (!$this->userAccessService->hasApiAccess($user->id)) {
            return redirect()->route('cabinet.tariff.index')
                ->with('error', 'Доступ к API не включён в вашем тарифе. Смените тариф или подключите addon «API доступ».');
        }

        $client = $this->resolveClient($user->id);
        $keys = $client
            ? $client->keys()->orderByDesc('id')->get()
            : collect();

        $plainKey = $request->session()->pull('api_plain_key');

        return view('cabinet.api_keys.index', [
            'client' => $client,
            'keys' => $keys,
            'plainKey' => $plainKey,
            'maxActiveKeys' => ApiKeyService::MAX_ACTIVE_KEYS_PER_CLIENT,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if (!$this->userAccessService->hasApiAccess($user->id)) {
            return back()->with('error', 'Доступ к API не включён в вашем тарифе.');
        }

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'ip_whitelist' => 'nullable|string|max:2000',
        ]);

        $whitelist = $this->parseWhitelist($data['ip_whitelist'] ?? null);

        $client = $this->resolveClient($user->id, createIfMissing: true);

        try {
            $result = $this->apiKeyService->generate($client, $data['name'], $whitelist);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'api_key_limit_reached') {
                return back()->with('error', 'Достигнут лимит активных ключей ('
                    . ApiKeyService::MAX_ACTIVE_KEYS_PER_CLIENT . '). Отзовите один из существующих.');
            }
            throw $e;
        }

        // Показываем ключ один раз через flash в сессии.
        $request->session()->flash('api_plain_key', [
            'name' => $data['name'],
            'plain_key' => $result['plain_key'],
        ]);

        return redirect()->route('cabinet.api-keys.index')
            ->with('success', 'Ключ создан. Сохраните его сейчас — показывается один раз.');
    }

    public function destroy(Request $request, ApiKey $key): RedirectResponse
    {
        $user = Auth::user();

        $client = $this->resolveClient($user->id);
        if (!$client || $key->api_client_id !== $client->id) {
            abort(404);
        }

        $this->apiKeyService->revoke($key);

        return redirect()->route('cabinet.api-keys.index')
            ->with('success', 'Ключ отозван. Он будет физически удалён через '
                . ApiKeyService::REVOKED_GRACE_DAYS . ' дней.');
    }

    private function resolveClient(int $userId, bool $createIfMissing = false): ?ApiClient
    {
        $client = ApiClient::query()->where('user_id', $userId)->first();
        if ($client || !$createIfMissing) {
            return $client;
        }

        return DB::transaction(function () use ($userId) {
            // updateOrCreate защищает от гонок при параллельном создании.
            return ApiClient::query()->firstOrCreate(
                ['user_id' => $userId],
                ['is_active' => true, 'overdraft_percent' => 20.00]
            );
        });
    }

    /**
     * Разбирает whitelist из textarea: по одному entry на строку.
     * Принимает IPv4 и CIDR /N.
     *
     * @return array<string>|null
     */
    private function parseWhitelist(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        $lines = preg_split('/[\s,]+/', $raw) ?: [];
        $clean = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            // Лёгкая валидация: IPv4 или IPv4/CIDR. Остальное — на совесть клиента.
            if (preg_match('/^\d{1,3}(\.\d{1,3}){3}(\/\d{1,2})?$/', $line)) {
                $clean[] = $line;
            }
        }
        return $clean ?: null;
    }
}
