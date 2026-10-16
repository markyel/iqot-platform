<?php

namespace Tests\Unit;

use App\Services\Generate\VisibleTokenLine;
use Tests\TestCase;

class VisibleTokenLineTest extends TestCase
{
    public function test_token_is_visible_and_not_hidden_text(): void
    {
        $html = VisibleTokenLine::render('LS-REED109-SI20-AB12', ['id' => 7]);
        $this->assertStringContainsString('LS-REED109-SI20-AB12', $html);
        $this->assertStringNotContainsString('font-size:1px', $html);
        $this->assertStringNotContainsString('#ffffff', $html);
        $this->assertStringNotContainsString('mso-hide', $html);
    }

    public function test_phrasing_is_stable_per_template_and_differs_between_templates(): void
    {
        $this->assertSame(VisibleTokenLine::render('T-1', ['id' => 11]), VisibleTokenLine::render('T-1', ['id' => 11]));
        $phrases = array_map(fn ($id) => VisibleTokenLine::phraseFor(['id' => $id]), range(1, 14));
        $this->assertCount(14, array_unique($phrases)); // 14 шаблонов подряд — 14 разных формулировок
    }

    public function test_template_can_set_its_own_label_and_token_is_escaped(): void
    {
        $html = VisibleTokenLine::render('A<b>', ['id' => 3], ['type' => 'token', 'label' => 'Наш № {token}']);
        $this->assertStringContainsString('Наш № A&lt;b&gt;', $html);
        $this->assertSame('', VisibleTokenLine::render('', ['id' => 3]));
    }
}
