<?php

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Models\Api\UserSender;
use App\Models\ClientOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Личный кабинет: управление senders (§9.3).
 *
 * Семантика:
 *  - Пользователь может иметь несколько user_senders.
 *  - Ровно один sender с is_default=1 (если их несколько; для единственного —
 *    default не обязателен, но ставим 1 для консистентности).
 *  - client_organization_id — логическая ссылка в reports.client_organizations,
 *    cross-DB (уникальность на пару (user_id, client_organization_id)).
 */
class SenderController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        $senders = UserSender::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        $organizations = ClientOrganization::getActiveForSelect();

        return view('cabinet.senders.index', [
            'senders' => $senders,
            'organizations' => $organizations,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'client_organization_id' => 'nullable|integer|min:1',
            'external_sender_id' => 'nullable|integer|min:1',
            'is_default' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($user, $data) {
            $sender = UserSender::create([
                'user_id' => $user->id,
                'client_organization_id' => $data['client_organization_id'] ?? null,
                'external_sender_id' => $data['external_sender_id'] ?? null,
                'is_active' => true,
                'is_default' => (bool) ($data['is_default'] ?? false),
            ]);

            if ($sender->is_default) {
                $this->demoteOtherDefaults($user->id, $sender->id);
            } elseif (!UserSender::where('user_id', $user->id)->where('is_default', true)->exists()) {
                // Нет default — пометим этот как default.
                $sender->update(['is_default' => true]);
            }
        });

        return redirect()->route('cabinet.senders.index')->with('success', 'Sender добавлен.');
    }

    public function update(Request $request, UserSender $sender): RedirectResponse
    {
        $this->assertOwn($sender);

        $data = $request->validate([
            'client_organization_id' => 'nullable|integer|min:1',
            'external_sender_id' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        $sender->update(array_filter([
            'client_organization_id' => $data['client_organization_id'] ?? $sender->client_organization_id,
            'external_sender_id' => $data['external_sender_id'] ?? $sender->external_sender_id,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $sender->is_active,
        ], fn ($v) => $v !== null));

        return redirect()->route('cabinet.senders.index')->with('success', 'Sender обновлён.');
    }

    public function makeDefault(UserSender $sender): RedirectResponse
    {
        $this->assertOwn($sender);

        DB::transaction(function () use ($sender) {
            $this->demoteOtherDefaults($sender->user_id, $sender->id);
            $sender->update(['is_default' => true]);
        });

        return redirect()->route('cabinet.senders.index')->with('success', 'Default-sender изменён.');
    }

    public function destroy(UserSender $sender): RedirectResponse
    {
        $this->assertOwn($sender);

        $wasDefault = $sender->is_default;
        $userId = $sender->user_id;

        $sender->delete();

        // Если удалили default — назначим новый default если есть кандидаты.
        if ($wasDefault) {
            $candidate = UserSender::query()
                ->where('user_id', $userId)
                ->orderBy('id')
                ->first();
            if ($candidate) {
                $candidate->update(['is_default' => true]);
            }
        }

        return redirect()->route('cabinet.senders.index')->with('success', 'Sender удалён.');
    }

    private function assertOwn(UserSender $sender): void
    {
        if ($sender->user_id !== Auth::id()) {
            abort(404);
        }
    }

    private function demoteOtherDefaults(int $userId, int $exceptId): void
    {
        UserSender::query()
            ->where('user_id', $userId)
            ->where('id', '!=', $exceptId)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
