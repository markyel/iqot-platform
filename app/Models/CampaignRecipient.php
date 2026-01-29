<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignRecipient extends Model
{
    protected $fillable = [
        'campaign_id',
        'email',
        'data',
        'status',
        'error_message',
        'sent_at',
        'email_validated',
        'validation_status',
        'validation_reason',
        'validation_provider',
        'validated_at',
    ];

    protected $casts = [
        'data' => 'array',
        'sent_at' => 'datetime',
        'email_validated' => 'boolean',
        'validated_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Помечает письмо как отправленное
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'error_message' => null,
        ]);
    }

    /**
     * Помечает письмо как неудачное
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
        ]);
    }

    /**
     * Помечает email как валидированный
     */
    public function markAsValidated(array $validationResult): void
    {
        $this->update([
            'email_validated' => true,
            'validation_status' => $validationResult['valid'] ? 'valid' : 'invalid',
            'validation_reason' => $validationResult['reason'] ?? null,
            'validation_provider' => $validationResult['provider'] ?? 'basic',
            'validated_at' => now(),
        ]);
    }

    /**
     * Проверяет, можно ли отправить письмо
     */
    public function canSend(): bool
    {
        // Если валидация была выполнена и email невалидный, не отправляем
        if ($this->email_validated && $this->validation_status === 'invalid') {
            return false;
        }

        // Не отправляем если уже отправлено или отписался
        if (in_array($this->status, ['sent', 'unsubscribed'])) {
            return false;
        }

        return true;
    }
}
