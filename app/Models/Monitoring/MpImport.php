<?php

namespace App\Models\Monitoring;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Асинхронный импорт номенклатуры в программу (API-пачка или файл).
 */
class MpImport extends Model
{
    protected $table = 'mp_imports';

    protected $fillable = [
        'external_id', 'program_id', 'source', 'idempotency_key', 'status',
        'rows_total', 'rows_ok', 'rows_failed', 'errors', 'payload_path',
    ];

    protected $casts = [
        'errors' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $i) {
            $i->external_id ??= (string) Str::ulid();
        });
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(MonitoringProgram::class, 'program_id');
    }
}
