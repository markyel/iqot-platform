<?php

namespace App\Jobs\Api;

use App\Models\Api\ApiKey;
use App\Services\Api\ApiKeyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Физически удаляет api_keys, отозванные более чем REVOKED_GRACE_DAYS дней назад (§9.5).
 * Scheduler: daily.
 */
class CleanupRevokedApiKeysJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public $tries = 1;

    public function __construct()
    {
        $this->onConnection('database');
    }

    public function handle(): void
    {
        $cutoff = now()->subDays(ApiKeyService::REVOKED_GRACE_DAYS);
        $deleted = ApiKey::query()
            ->whereNotNull('revoked_at')
            ->where('revoked_at', '<', $cutoff)
            ->delete();
        if ($deleted > 0) {
            Log::info('CleanupRevokedApiKeysJob: keys deleted', ['count' => $deleted]);
        }
    }
}
