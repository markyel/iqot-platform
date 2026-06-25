<?php

namespace App\Models\Reports;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Письмо в очереди рассылки (reports.email_queue).
 *
 * Жизненный цикл статусов: pending → sending → sent | error.
 * `sending` используется диспетчером как «claim» (письмо взято в работу),
 * чтобы повторный тик не подхватил его второй раз.
 */
class EmailQueue extends Model
{
    protected $connection = 'reports';
    protected $table = 'email_queue';

    protected $guarded = ['id'];

    protected $casts = [
        'priority' => 'integer',
        'retry_count' => 'integer',
        'max_retries' => 'integer',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'replied_at' => 'datetime',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Sender::class, 'sender_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(EmailBatch::class, 'batch_id');
    }
}
