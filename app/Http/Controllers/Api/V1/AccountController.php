<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Api\ApiClient;
use App\Models\BalanceHold;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /**
     * GET /api/v1/account/balance — §11.10.
     */
    public function balance(Request $request): JsonResponse
    {
        /** @var ApiClient $client */
        $client = $request->attributes->get('api_client');
        $requestId = (string) $request->attributes->get('api_request_id');

        $user = User::findOrFail($client->user_id);

        $activeHolds = (float) BalanceHold::query()
            ->where('user_id', $user->id)
            ->where('status', 'held')
            ->sum('amount');

        $overdraftPercent = (float) $client->overdraft_percent;
        // Абсолютный overdraft считается от активных holds (спека §10.4 считает от required_hold;
        // для GET /balance показываем на базе текущих holds как индикатор).
        $overdraftAbsolute = round($activeHolds * $overdraftPercent / 100.0, 2);

        $warning = null;
        if ((float) $user->balance < 0) {
            $warning = sprintf('overdraft_%s_rub', abs((float) $user->balance));
        }

        return response()->json([
            'balance' => (float) $user->balance,
            'currency' => 'RUB',
            'active_holds' => $activeHolds,
            'overdraft_limit_percent' => $overdraftPercent,
            'overdraft_limit_absolute' => $overdraftAbsolute,
            'warning' => $warning,
        ])->header('X-Request-Id', $requestId);
    }
}
