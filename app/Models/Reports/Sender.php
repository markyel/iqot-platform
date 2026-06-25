<?php

namespace App\Models\Reports;

use Illuminate\Database\Eloquent\Model;

/**
 * Отправитель рассылки (reports.senders).
 *
 * Таблица живёт во внешней БД price_quotation_system (connection `reports`,
 * та же, что использует n8n). Сидируется вручную либо через групповое
 * добавление в админке (аналог Telegram-команды /addmail).
 */
class Sender extends Model
{
    protected $connection = 'reports';
    protected $table = 'senders';

    protected $fillable = [
        'sender_name',
        'sender_full_name',
        'phone',
        'phone_normalized',
        'email',
        'smtp_server',
        'smtp_port',
        'smtp_user',
        'smtp_password',
        'smtp_encryption',
        'imap_server',
        'imap_port',
        'imap_user',
        'imap_password',
        'imap_encryption',
        'client_organization_id',
        'token_template_id',
        'template_id',
        'preferred_template_id',
        'daily_limit',
        'is_active',
        'is_verified',
        'email_style',
        'email_greeting',
        'token_style',
        'blocked_until',
        'block_count',
        'block_reason',
        'last_block_at',
        'send_delay_seconds',
        'emails_sent_today',
        'last_send_date',
        'last_send_at',
    ];

    protected $casts = [
        'smtp_port' => 'integer',
        'imap_port' => 'integer',
        'daily_limit' => 'integer',
        'is_active' => 'boolean',
        'is_verified' => 'boolean',
        'blocked_until' => 'datetime',
        'last_block_at' => 'datetime',
        'last_send_date' => 'date',
        'last_send_at' => 'datetime',
        'block_count' => 'integer',
        'send_delay_seconds' => 'integer',
        'emails_sent_today' => 'integer',
    ];
}
