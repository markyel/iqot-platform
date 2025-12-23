<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'website',
        'contact_person',
        'categories',
        'brands',
        'description',
        'rating',
        'response_rate',
        'avg_response_time',
        'total_requests',
        'total_responses',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'brands' => 'array',
            'rating' => 'decimal:2',
            'response_rate' => 'decimal:2',
            'avg_response_time' => 'integer', // в минутах
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * Предложения от поставщика
     */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    /**
     * Заявки, в которых участвовал поставщик
     */
    public function requests()
    {
        return $this->belongsToMany(Request::class, 'request_suppliers')
            ->withPivot(['status', 'sent_at', 'responded_at'])
            ->withTimestamps();
    }

    /**
     * Пересчёт статистики
     */
    public function recalculateStats(): void
    {
        $totalRequests = $this->requests()->count();
        $totalResponses = $this->requests()->wherePivot('status', 'responded')->count();
        
        $responseRate = $totalRequests > 0 
            ? ($totalResponses / $totalRequests) * 100 
            : 0;

        $this->update([
            'total_requests' => $totalRequests,
            'total_responses' => $totalResponses,
            'response_rate' => $responseRate,
        ]);
    }

    /**
     * Поиск поставщиков по категориям/брендам
     */
    public function scopeForCategories($query, array $categories)
    {
        return $query->where(function ($q) use ($categories) {
            foreach ($categories as $category) {
                $q->orWhereJsonContains('categories', $category);
            }
        });
    }

    public function scopeForBrands($query, array $brands)
    {
        return $query->where(function ($q) use ($brands) {
            foreach ($brands as $brand) {
                $q->orWhereJsonContains('brands', $brand);
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
