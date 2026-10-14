<?php

namespace App\Models\Monitoring;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Позиция программы мониторинга: как её прислал клиент (raw_*) + канонический товар.
 */
class ProgramItem extends Model
{
    public const MATCH_PENDING = 'pending';
    public const MATCH_MATCHED = 'matched';
    public const MATCH_AMBIGUOUS = 'ambiguous';
    public const MATCH_REJECTED = 'rejected';

    protected $fillable = [
        'program_id', 'client_ref', 'raw_name', 'raw_brand', 'raw_article', 'raw_unit',
        'raw_attributes', 'product_id', 'match_status', 'match_note', 'active', 'priority',
        'last_priced_at', 'price_source', 'prices_changed_at', 'next_due_at',
    ];

    protected $casts = [
        'raw_attributes' => 'array',
        'active' => 'boolean',
        'last_priced_at' => 'datetime',
        'prices_changed_at' => 'datetime',
        'next_due_at' => 'datetime',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(MonitoringProgram::class, 'program_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MpProduct::class, 'product_id');
    }
}
