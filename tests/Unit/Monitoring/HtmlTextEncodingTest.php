<?php

namespace Tests\Unit\Monitoring;

use App\Support\Monitoring\HtmlText;
use Tests\TestCase;

class HtmlTextEncodingTest extends TestCase
{
    public function test_truncated_utf8_with_declared_charset_is_not_treated_as_cp1251(): void
    {
        $html = '<html><head><meta charset="UTF-8"></head><body><h1>Электродвигатель FT100</h1>Ж';
        $cut = substr($html, 0, -1); // обрезка посреди двухбайтной «Ж» (как по max_bytes)
        $out = HtmlText::toUtf8($cut, 'text/html; charset=UTF-8');

        $this->assertTrue(mb_check_encoding($out, 'UTF-8'));
        $this->assertStringContainsString('Электродвигатель FT100', $out);
    }

    public function test_undeclared_cp1251_is_converted(): void
    {
        $cp = mb_convert_encoding('<html><body>Ролик натяжной</body></html>', 'Windows-1251', 'UTF-8');
        $this->assertStringContainsString('Ролик натяжной', HtmlText::toUtf8($cp, 'text/html'));
    }
}
