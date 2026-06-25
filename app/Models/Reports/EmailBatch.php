<?php

namespace App\Models\Reports;

use Illuminate\Database\Eloquent\Model;

/**
 * Батч рассылки (reports.email_batches).
 *
 * Группирует письма очереди (email_queue) по одной заявке/отправителю.
 * `request_items` — JSON-массив id позиций заявки (для подгрузки вложений).
 */
class EmailBatch extends Model
{
    protected $connection = 'reports';
    protected $table = 'email_batches';

    // В таблице нет updated_at — отключаем авто-таймстемпы.
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'request_items' => 'array',
        'supplier_ids' => 'array',
        'is_customer_request' => 'boolean',
        'items_count' => 'integer',
        'suppliers_count' => 'integer',
        'queued_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
