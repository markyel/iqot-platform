<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParseTask extends Model
{
    protected $fillable = [
        'task_id',
        'user_id',
        'text',
        'status',
        'result',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'result' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Статусы задачи
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    /**
     * Отношение к пользователю
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Пометить как начатую
     */
    public function markAsProcessing()
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
    }

    /**
     * Пометить как завершенную
     */
    public function markAsCompleted(array $result)
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'result' => $result,
            'completed_at' => now(),
        ]);
    }

    /**
     * Пометить как провалившуюся
     */
    public function markAsFailed(string $errorMessage)
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    /**
     * Проверить, завершена ли задача
     */
    public function isCompleted(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED]);
    }

    /**
     * Получить результат для API
     */
    public function toApiResponse(): array
    {
        $response = [
            'task_id' => $this->task_id,
            'status' => $this->status,
        ];

        if ($this->status === self::STATUS_COMPLETED && $this->result) {
            $response['success'] = true;
            $response['items'] = $this->result['items'] ?? [];
            $response['items_count'] = $this->result['items_count'] ?? count($this->result['items'] ?? []);
            $response['has_new_classifications'] = $this->result['has_new_classifications'] ?? false;
            $response['updated_product_types'] = $this->result['updated_product_types'] ?? null;
            $response['updated_application_domains'] = $this->result['updated_application_domains'] ?? null;
        } elseif ($this->status === self::STATUS_FAILED) {
            $response['success'] = false;
            $response['message'] = $this->error_message;
        } else {
            $response['success'] = false;
            $response['message'] = 'Задача еще обрабатывается...';
        }

        return $response;
    }
}
