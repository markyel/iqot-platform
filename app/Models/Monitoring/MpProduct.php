<?php

namespace App\Models\Monitoring;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Канонический товар мониторинга — общий для всех клиентов. Поиск листингов, обход
 * и доверие к сайтам делаются один раз на товар.
 */
class MpProduct extends Model
{
    protected $table = 'mp_products';

    protected $fillable = [
        'name', 'brand', 'brand_norm', 'unit_kind', 'product_type_id', 'domain_id',
        'classification_source', 'search_state', 'last_search_at', 'next_search_at', 'search_attempts',
        'listings_active', 'listings_priced', 'merged_into_id',
    ];

    protected $casts = [
        'last_search_at' => 'datetime',
        'next_search_at' => 'datetime',
    ];

    public function identifiers(): HasMany
    {
        return $this->hasMany(MpIdentifier::class, 'product_id');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(MpListing::class, 'product_id');
    }

    /** Нормализованные коды товара (oem/mpn) — для проверки страницы «по токену». */
    public function articleCodes(): array
    {
        return $this->codeIdentifiers()->pluck('value_norm')->filter()->unique()->values()->all();
    }

    /** Исходные написания кодов (для промпта и окна текста). */
    public function articleValues(): array
    {
        return $this->codeIdentifiers()->pluck('value')->filter()->unique()->values()->all();
    }

    /**
     * OEM/MPN + коды-алиасы, похожие на код модели (напр. каталожный код клиента
     * «LW-0002478» — партнёрские сайты LazyLift ставят его в sku). Алиасы-наименования
     * (товар без артикула) сюда не попадают.
     */
    private function codeIdentifiers(): \Illuminate\Support\Collection
    {
        return $this->identifiers->filter(fn (MpIdentifier $i) => in_array($i->kind, [MpIdentifier::KIND_OEM, MpIdentifier::KIND_MPN], true)
            || ($i->kind === MpIdentifier::KIND_ALIAS && mb_strlen((string) $i->value_norm) <= 30
                && !str_contains((string) $i->value_norm, ' ')
                && \App\Support\Monitoring\ArticleNormalizer::looksLikeModelCode((string) $i->value_norm)));
    }
}
