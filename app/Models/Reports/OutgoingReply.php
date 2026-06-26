<?php

namespace App\Models\Reports;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Готовый ответ поставщику в очереди отправки (reports.outgoing_replies).
 *
 * Создаётся триажом вопросов (emails:process-questions, status='pending'),
 * отправляется диспетчером emails:dispatch-replies (замена n8n «Send Outgoing
 * Replies»). Жизненный цикл статусов: pending → sending → sent | failed.
 * `sending` — «claim» диспетчера (ответ взят в работу), чтобы повторный тик не
 * подхватил его второй раз.
 */
class OutgoingReply extends Model
{
    protected $connection = 'reports';
    protected $table = 'outgoing_replies';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'conversation_id' => 'integer',
        'supplier_question_id' => 'integer',
        'sender_id' => 'integer',
        'supplier_id' => 'integer',
        'retry_count' => 'integer',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Sender::class, 'sender_id');
    }
}
