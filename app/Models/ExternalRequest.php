<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalRequest extends Model
{
    protected $connection = 'reports';
    protected $table = 'requests';

    protected $fillable = [
        'user_id',
        'client_organization_id',
        'request_number',
        'title',
        'status',
        'collection_deadline',
        'completion_percentage',
        'total_items',
        'items_with_offers',
        'notes',
        'completed_at',
        'is_customer_request',
        'customer_company',
        'customer_contact_person',
        'customer_email',
        'customer_phone',
        'customer_notes',
        'parent_request_id',
        'is_retry',
    ];

    protected $casts = [
        'collection_deadline' => 'datetime',
        'completed_at' => 'datetime',
        'completion_percentage' => 'decimal:2',
        'is_customer_request' => 'boolean',
        'is_retry' => 'boolean',
        'total_items' => 'integer',
        'items_with_offers' => 'integer',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_NEW = 'new';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COLLECTING = 'collecting';
    public const STATUS_QUEUED_FOR_SENDING = 'queued_for_sending';
    public const STATUS_EMAILS_SENT = 'emails_sent';
    public const STATUS_RESPONSES_RECEIVED = 'responses_received';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_DRAFT => 'Черновик',
            self::STATUS_NEW => 'Новая',
            self::STATUS_ACTIVE => 'Активна',
            self::STATUS_COLLECTING => 'Сбор',
            self::STATUS_QUEUED_FOR_SENDING => 'В очереди',
            self::STATUS_EMAILS_SENT => 'Отправлено',
            self::STATUS_RESPONSES_RECEIVED => 'Получены ответы',
            self::STATUS_COMPLETED => 'Завершена',
            self::STATUS_CANCELLED => 'Отменена',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ExternalRequestItem::class, 'request_id');
    }
}
