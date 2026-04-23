<?php

namespace App\Http\Middleware;

use App\Models\Api\ApiClient;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Проставляет заголовок `X-Balance-Warning: overdraft_<amount>_rub`
 * когда у пользователя api_client отрицательный баланс (§10.5).
 *
 * Работает после `api.auth` (ожидает в attributes 'api_client').
 */
class ApiBalanceWarning
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        /** @var ApiClient|null $client */
        $client = $request->attributes->get('api_client');
        if ($client && $response instanceof Response) {
            $user = User::find($client->user_id);
            if ($user && (float) $user->balance < 0) {
                $amount = number_format(abs((float) $user->balance), 2, '.', '');
                $response->headers->set('X-Balance-Warning', "overdraft_{$amount}_rub");
            }
        }
        return $response;
    }
}
