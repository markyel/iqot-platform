<?php

namespace App\Models\Monitoring;

use App\Models\Api\ApiClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Программа мониторинга цен клиента: список номенклатуры + режим получения цены
 * (lite|hybrid) + целевая свежесть. В отличие от заявки — не закрывается.
 */
class MonitoringProgram extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'external_id', 'api_client_id', 'user_id', 'name', 'price_mode',
        'target_freshness_days', 'site_refresh_days', 'email_refresh_days', 'discovery_rate',
        'status', 'paused_reason', 'items_total', 'items_active', 'items_matched',
        'items_ambiguous', 'items_covered', 'webhook_url', 'settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'target_freshness_days' => 'integer',
        'site_refresh_days' => 'integer',
        'email_refresh_days' => 'integer',
        'discovery_rate' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $p) {
            $p->external_id ??= (string) Str::ulid();
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProgramItem::class, 'program_id');
    }

    public function imports(): HasMany
    {
        return $this->hasMany(MpImport::class, 'program_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function apiClient(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class);
    }

    /** Пересчёт денормализованных счётчиков позиций. */
    public function refreshCounters(): void
    {
        $base = ProgramItem::query()->where('program_id', $this->id);
        $this->forceFill([
            'items_total' => (clone $base)->count(),
            'items_active' => (clone $base)->where('active', true)->count(),
            'items_matched' => (clone $base)->where('active', true)->where('match_status', 'matched')->count(),
            'items_ambiguous' => (clone $base)->where('active', true)->where('match_status', 'ambiguous')->count(),
        ])->save();
    }
}
