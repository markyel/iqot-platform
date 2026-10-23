<?php

namespace Tests\Unit\Monitoring;

use App\Models\Monitoring\MpIdentifier;
use App\Models\Monitoring\MpProduct;
use App\Services\Monitoring\Extract\ListingIdentityVerifier;
use App\Support\Monitoring\ArticleNormalizer;
use App\Support\Monitoring\KeyAttributes;
use Tests\TestCase;

/** M01525 (23.10.2026): страницы без артикула принимались по общему «Плата KLEEMANN». */
class KeyAttributesModelTest extends TestCase
{
    private function attrs(): array
    {
        return KeyAttributes::normalize([
            ['key' => 'модель', 'value' => 'KD-10', 'kind' => 'model', 'match' => ['kd-10', 'kd10'], 'conflict' => []],
            ['key' => 'ток', 'value' => '6A', 'kind' => 'electrical', 'match' => ['6a'], 'conflict' => []],
            ['key' => 'бренд', 'value' => 'KLEEMANN', 'kind' => 'brand', 'match' => ['kleemann'], 'conflict' => []],
        ]);
    }

    public function test_model_must_be_named(): void
    {
        $a = $this->attrs();
        $this->assertNull(KeyAttributes::missingModel($a, 'Плата KLEEMANN (климанн) KD-10 6A без соединителей'));
        $this->assertNull(KeyAttributes::missingModel($a, 'product plata kleemann kd10 6a'));
        $this->assertSame('модель: KD-10', KeyAttributes::missingModel($a, 'Плата тормоза эскалатора KLEEMANN'));
        $this->assertSame('модель: KD-10', KeyAttributes::missingModel($a, 'Плата управления LISA 10-X 6 60-01-80013 10171017 Kleemann'));
        // Кириллица-двойник латиницы: «КD-10» с русской К — та же модель.
        $this->assertNull(KeyAttributes::missingModel($a, 'Плата КD-10 6A'));
        // Нет признака модели — требовать нечего.
        $this->assertNull(KeyAttributes::missingModel([$a[2]], 'Плата тормоза эскалатора KLEEMANN'));
    }

    public function test_verifier_rejects_llm_match_without_model(): void
    {
        $p = new MpProduct(['name' => 'Плата KD-10 6A', 'brand' => 'KLEEMANN', 'key_attrs' => $this->attrs()]);
        $p->setRelation('identifiers', collect([new MpIdentifier(['kind' => 'oem', 'value' => '10039327', 'value_norm' => ArticleNormalizer::normalize('10039327')])]));
        $v = new ListingIdentityVerifier();
        $page = 'Плата тормоза эскалатора KLEEMANN цена по запросу';
        $r = $v->verify($p, $page, null, ['is_same_product' => true, 'is_analog' => false, 'attrs' => [],
            'evidence_identity' => 'Плата тормоза эскалатора KLEEMANN'], 'category zapchasti ehskalatorov kleemann plata tormoza ehskalatora kleemann');
        $this->assertFalse($r['accepted']);
        $this->assertSame('attr_missing', $r['reason']);

        $ok = $v->verify($p, 'Плата KLEEMANN (климанн) KD-10 6A без соединителей', null, ['is_same_product' => true, 'is_analog' => false, 'attrs' => [],
            'evidence_identity' => 'Плата KLEEMANN (климанн) KD-10 6A без соединителей'], 'product plata kleemann klimann kd 10 6a');
        $this->assertTrue($ok['accepted']);
    }
}
