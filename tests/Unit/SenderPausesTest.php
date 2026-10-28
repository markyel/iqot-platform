<?php

namespace Tests\Unit;

use App\Services\Senders\SenderPauses;
use PHPUnit\Framework\TestCase;

class SenderPausesTest extends TestCase
{
    public function test_pause_grows_by_a_week_per_repeat(): void
    {
        $this->assertSame(7, SenderPauses::durationDays(1, 7, 7));
        $this->assertSame(14, SenderPauses::durationDays(2, 7, 7));
        $this->assertSame(21, SenderPauses::durationDays(3, 7, 7));
    }

    public function test_blocks_by_mailbox_or_domain(): void
    {
        $active = [
            'info@b2b-postavki.ru' => ['m' => [166 => true], 'd' => []],
            'zakaz@delifto.ru' => ['m' => [], 'd' => ['doclift.ru' => true]],
        ];
        // Ящик на паузе — только он, соседний ящик того же домена пишет.
        $this->assertTrue(SenderPauses::blocks($active, 'INFO@b2b-postavki.ru ', 166, 'reutkomfort@doclift.ru'));
        $this->assertFalse(SenderPauses::blocks($active, 'info@b2b-postavki.ru', 3, 'snab@doclift.ru'));
        // Домен на паузе — любой его ящик; другой домен пишет.
        $this->assertTrue(SenderPauses::blocks($active, 'zakaz@delifto.ru', 3, 'snab@doclift.ru'));
        $this->assertFalse(SenderPauses::blocks($active, 'zakaz@delifto.ru', 276, 'm.kuzmina@eespc.ru'));
        // Получатель без пауз.
        $this->assertFalse(SenderPauses::blocks($active, 'other@x.ru', 166, 'reutkomfort@doclift.ru'));
    }

    public function test_pairs_for_planners(): void
    {
        $active = ['zakaz@delifto.ru' => ['m' => [], 'd' => ['doclift.ru' => true]]];
        $pairs = SenderPauses::pairs($active, [3 => 'snab@doclift.ru', 166 => 'reutkomfort@doclift.ru', 276 => 'm.kuzmina@eespc.ru']);
        $this->assertSame(['3|zakaz@delifto.ru' => true, '166|zakaz@delifto.ru' => true], $pairs);
    }
}
