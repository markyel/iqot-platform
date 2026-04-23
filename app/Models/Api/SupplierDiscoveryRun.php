<?php

namespace App\Models\Api;

use App\Models\ApplicationDomain;
use App\Models\ProductType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierDiscoveryRun extends Model
{
    protected $connection = 'reports';
    protected $table = 'supplier_discovery_runs';

    public const UPDATED_AT = null;

    protected $fillable = [
        'domain_id',
        'product_type_id',
        'status',
        'iterations_used',
        'suppliers_found',
        'trigger_source',
        'triggering_submission_external_id',
        'started_at',
        'finished_at',
        'error',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class, 'product_type_id');
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(ApplicationDomain::class, 'domain_id');
    }
}
