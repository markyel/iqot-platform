<?php

namespace App\Jobs\Monitoring;

use App\Models\Monitoring\MpListing;
use App\Services\Monitoring\ListingChecker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Проверка одного листинга (очередь monitor). Слот домена занят → release без
 * роста попыток (tries=0 + retryUntil, как SendQueuedEmailJob), воркер не ждёт.
 */
class CheckListingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public int $tries = 0;

    private const MAX_DEFERRALS = 40;

    public function __construct(private readonly int $listingId)
    {
        $this->onQueue('monitor');
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(6);
    }

    public function handle(): void
    {
        $lock = Cache::lock("monitor:listing:{$this->listingId}", 170);
        if (!$lock->get()) {
            return;
        }
        try {
            $listing = MpListing::find($this->listingId);
            if (!$listing) {
                return;
            }
            $result = ListingChecker::make()->check($listing);

            if (($result['status'] ?? null) === 'deferred') {
                if ($this->attempts() >= self::MAX_DEFERRALS) {
                    $listing->forceFill(['next_check_at' => now()->addMinutes(30)])->save();

                    return;
                }
                $this->release(max(1, (int) ceil(($result['retry_after_ms'] ?? 1000) / 1000)));
            }
        } finally {
            $lock->release();
        }
    }
}
