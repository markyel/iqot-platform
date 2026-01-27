<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $fillable = [
        'name',
        'subject',
        'html_template',
        'field_mapping',
        'status',
        'total_recipients',
        'sent_count',
        'failed_count',
        'delay_seconds',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'field_mapping' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(CampaignImage::class);
    }

    /**
     * Извлекает переменные из HTML-шаблона (например, {{Name}}, {{PromoCode}})
     */
    public function extractTemplateVariables(): array
    {
        preg_match_all('/\{\{(\w+)\}\}/', $this->html_template, $matches);
        return array_unique($matches[1]);
    }

    /**
     * Извлекает все изображения из HTML-шаблона
     * Возвращает массив с уникальными src изображений
     */
    public function extractTemplateImages(): array
    {
        $images = [];
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $this->html_template, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $src) {
                // Пропускаем data: URI и абсолютные http/https URL
                if (!preg_match('/^(data:|https?:\/\/)/i', $src)) {
                    $images[] = $src;
                }
            }
        }

        return array_unique($images);
    }

    /**
     * Заменяет переменные в шаблоне на реальные данные
     */
    public function renderTemplate(array $data): string
    {
        $html = $this->html_template;
        foreach ($data as $key => $value) {
            $html = str_replace('{{' . $key . '}}', $value, $html);
        }
        return $html;
    }

    /**
     * Проверяет, можно ли редактировать рассылку
     */
    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'failed']);
    }

    /**
     * Проверяет, можно ли запустить рассылку
     */
    public function canStart(): bool
    {
        return $this->status === 'draft' && $this->total_recipients > 0;
    }
}
