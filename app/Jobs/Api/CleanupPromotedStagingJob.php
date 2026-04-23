<?php

namespace App\Jobs\Api;

use App\Models\Api\RequestItemStaging;
use App\Models\Api\RequestStaging;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Удаляет promoted staging items submission спустя 5 минут после промоушена (§6.4 шаг 3).
 * Если все items стаджинга удалены — удаляет и сам request_staging (stage=finalised).
 */
class CleanupPromotedStagingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public $tries = 3;

    public int $submissionId;

    public function __construct(int $submissionId)
    {
        $this->submissionId = $submissionId;
        $this->onConnection('database');
    }

    public function handle(): void
    {
        $staging = RequestStaging::query()
            ->where('api_submission_id', $this->submissionId)
            ->first();
        if (!$staging) {
            return;
        }

        RequestItemStaging::query()
            ->where('request_staging_id', $staging->id)
            ->where('item_status', 'promoted')
            ->delete();

        // Если после удаления не осталось items — удаляем staging-запись.
        $remaining = RequestItemStaging::query()
            ->where('request_staging_id', $staging->id)
            ->count();
        if ($remaining === 0 && $staging->stage === 'finalised') {
            $staging->delete();
        }
    }
}
