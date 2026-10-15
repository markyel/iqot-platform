<?php

namespace App\Models\Monitoring;

use App\Support\Monitoring\UrlCanonicalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Профиль сайта поставщика (registrable domain): режим загрузки, блокировка,
 * вежливость, доверие к ценам (логика доверия — Ф3).
 */
class MpSite extends Model
{
    protected $table = 'mp_sites';

    protected $fillable = [
        'domain', 'supplier_id', 'kind', 'publishes_prices', 'publishes_stock',
        'has_structured_markup', 'search_url_template', 'sitemap_url', 'sitemap_indexed_at', 'sitemap_pages', 'fetch_mode', 'egress',
        'blocked_until', 'blocked_reason', 'rate_limit_ms', 'trust_level', 'trust_scope',
        'sample_every', 'verified_ok', 'verified_mismatch', 'consecutive_ok', 'consecutive_mismatch',
        'last_verified_at', 'next_verification_due_at', 'stats_30d',
    ];

    protected $casts = [
        'publishes_prices' => 'boolean',
        'publishes_stock' => 'boolean',
        'has_structured_markup' => 'boolean',
        'blocked_until' => 'datetime',
        'sitemap_indexed_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'next_verification_due_at' => 'datetime',
        'stats_30d' => 'array',
    ];

    public static function forUrl(string $url): self
    {
        $domain = UrlCanonicalizer::registrableDomain($url);

        return self::query()->firstOrCreate(['domain' => $domain], [
            'rate_limit_ms' => (int) config('monitoring.fetch.default_rate_ms', 1000),
        ]);
    }

    public function listings(): HasMany
    {
        return $this->hasMany(MpListing::class, 'site_id');
    }

    public function isBlocked(): bool
    {
        return $this->blocked_until !== null && $this->blocked_until->isFuture();
    }

    public function block(string $reason): void
    {
        $this->forceFill([
            'blocked_until' => now()->addDays((int) config('monitoring.fetch.block_days', 7)),
            'blocked_reason' => mb_substr($reason, 0, 120),
        ])->save();
    }
}
