<?php

namespace App\Services\Generate;

/**
 * Дословный порт n8n-узла «Generate Emails v7.9.1» на ОДНОГО поставщика.
 *
 * Рендерит уникальный HTML письма (шапка/блоки/таблица/подпись/скрытый 1px-токен)
 * по шаблону отправителя (email_templates.blocks/items_format/items_display_config/
 * signature_format/style_preset/subject_template). Вёрстка/цвета/лейблы и логика
 * обогащения названий — 1:1 к JS. Per-supplier суффикс токена даёт уникальный
 * трекинг-токен на каждого поставщика.
 */
class CampaignEmailBuilder
{
    private const EMPTY_VAL = '—';

    /**
     * @param array<string,mixed> $supplier id,name,email,contact_person,categories
     * @return array<string,mixed> строка для email_queue (+ метаданные)
     */
    public function build(Batch $batch, array $supplier): array
    {
        $template = $batch->emailTemplate ?? [];
        $sender = $batch->sender ?? [];
        $items = $batch->items;

        // AI-сгенерированный контент.
        $aiContent = [
            'greeting' => $batch->aiBody['greeting'] ?? null,
            'introduction' => $batch->aiBody['introduction'] ?? null,
            'closing' => $batch->aiBody['closing'] ?? null,
        ];

        // Информация об отправителе.
        $senderName = ($sender['sender_name'] ?? null) ?: 'Отдел закупок';
        $senderEmail = $sender['email'] ?? null;
        $senderPhone = $sender['phone'] ?? null;
        $senderFullName = ($sender['sender_full_name'] ?? null) ?: (($sender['sender_name'] ?? null) ?: $senderName);
        $senderPosition = $sender['position'] ?? null; // в Get Sender нет → null

        $senderOrganization = null;
        if (!empty($sender['client_organization_id'])) {
            $senderOrganization = [
                'name' => $sender['organization_name'] ?? null,
                'inn' => $sender['organization_inn'] ?? null,
                'kpp' => $sender['organization_kpp'] ?? null,
                'legal_address' => $sender['organization_legal_address'] ?? null,
                'actual_address' => $sender['organization_actual_address'] ?? null,
                'phone' => $sender['organization_phone'] ?? null,
                'email' => $sender['organization_email'] ?? null,
                'director_name' => $sender['organization_director_name'] ?? null,
            ];
        }

        $baseToken = $batch->trackingToken;

        $displayConfig = $this->decodeJson($template['items_display_config'] ?? null);
        $globalStyle = $displayConfig['style'] ?? [];

        $blocks = $this->decodeJson($template['blocks'] ?? null);
        if (!is_array($blocks)) {
            $blocks = [];
        }

        // ── Per-supplier токен ──────────────────────────────────────────────
        $suffix = $this->generateTokenSuffix((string) ($supplier['name'] ?? ''));
        $token = "{$baseToken}-{$suffix}";

        // Тема письма (обогащённое название первого товара).
        $format = $template['items_format'] ?? 'table';
        $enrichedItems = $this->prepareItemsForRender($items, $format, $displayConfig);
        $firstItemName = $this->cleanValue($enrichedItems[0]['name'] ?? null, 'оборудование');

        $requestNumber = $batch->requestNumbers[0] ?? '';
        $subject = (string) ($template['subject_template'] ?? '');
        $subject = $this->replaceFirst($subject, '{{tracking_token}}', $token);
        $subject = $this->replaceFirst($subject, '{{request_number}}', (string) $requestNumber);
        $subject = $this->replaceFirst($subject, '{{item_name}}', (string) $firstItemName);

        // ── HTML ────────────────────────────────────────────────────────────
        $emailHTML = "<!DOCTYPE html>\n<html>\n<head><meta charset=\"UTF-8\"></head>\n"
            . "<body style=\"font-family:Arial,sans-serif;line-height:1.6;color:#333;margin:0;padding:0;\">\n"
            . "<div style=\"padding:10px;\">";

        $context = [
            'items' => $items,
            'template' => $template,
            'displayConfig' => $displayConfig,
            'aiContent' => $aiContent,
            'sender' => [
                'sender_name' => $senderName,
                'sender_full_name' => $senderFullName,
                'position' => $senderPosition,
                'phone' => $senderPhone,
                'email' => $senderEmail,
            ],
            'organization' => $senderOrganization,
            'token' => $token,
        ];

        // #4 группа A: намёк со ссылками на товар у поставщика на сайте. Встраиваем
        // В ТЕКСТ письма — ПЕРЕД вводным абзацем (ai_introduction), а не приклеиваем
        // под подпись: так письмо читается естественно («нашли у вас на сайте … →
        // помогите с заявкой» → далее обычный запрос КП).
        $foundHtml = $this->renderFoundBlock(
            $batch->aiBody['found_intro'] ?? null,
            is_array($supplier['found_urls'] ?? null) ? $supplier['found_urls'] : [],
            $globalStyle,
        );
        $foundInjected = ($foundHtml === '');

        foreach ($blocks as $block) {
            $blockType = is_array($block) ? (string) ($block['type'] ?? '') : '';
            if (!$foundInjected && in_array($blockType, ['ai_introduction', 'items_display'], true)) {
                $emailHTML .= $foundHtml;
                $foundInjected = true;
            }
            $emailHTML .= $this->renderBlock((array) $block, $context, $globalStyle);
        }
        // Фолбэк: в шаблоне нет ни ai_introduction, ни items_display — не теряем блок.
        if (!$foundInjected) {
            $emailHTML .= $foundHtml;
        }

        $emailHTML .= "</div>\n</body>\n</html>";

        return [
            'batch_id' => $batch->batchId,
            'supplier_id' => $supplier['id'] ?? null,
            'to_email' => $supplier['email'] ?? null,
            'supplier_name' => $supplier['name'] ?? null,
            'supplier_contact_person' => $supplier['contact_person'] ?? null,
            'sender_id' => $sender['id'] ?? null,
            'from_email' => $senderEmail,
            'sender_name' => $senderName,
            'subject' => $subject,
            'body_html' => $emailHTML,
            'tracking_token' => $token,
            'status' => 'pending',
            'request_ids' => $batch->requestIds,
            'request_numbers' => $batch->requestNumbers,
            'request_item_ids' => array_map(static fn ($it) => $it['id'] ?? null, $items),
            'items_count' => $batch->itemsCount,
            'ai_used' => (bool) ($aiContent['greeting'] || $aiContent['introduction'] || $aiContent['closing']),
        ];
    }

    /**
     * #4 группа A: намёк со ссылками на товар у поставщика на сайте. Встраивается
     * В ТЕКСТ письма (перед вводным абзацем) обычными абзацами в стиле тела —
     * БЕЗ выделенной рамки, чтобы читалось естественно, а не как приписка снизу.
     * Пусто, если поставщик не из группы A. found_intro — в стиле sender (из aiBody).
     *
     * @param array<int,array{url:string,item_id:int,item_name:string}> $foundUrls
     * @param array<string,mixed> $style стиль тела (font_size/text_color/header_bg)
     */
    private function renderFoundBlock(?string $intro, array $foundUrls, array $style = []): string
    {
        if ($foundUrls === []) {
            return '';
        }

        $fontSize = $style['font_size'] ?? '10pt';
        $textColor = $style['text_color'] ?? '#333';
        $linkColor = $style['header_bg'] ?? '#2c5aa0';
        $textStyles = "font-size:{$fontSize};color:{$textColor};line-height:1.6;";

        $intro = trim((string) $intro);
        if ($intro === '' || mb_strlen($intro) < 10) {
            $intro = 'Мы нашли часть позиций у вас на сайте — похоже, это то, что нам нужно. Будем признательны, если поможете проработать заявку целиком.';
        }
        $introHtml = htmlspecialchars($intro, ENT_QUOTES, 'UTF-8');

        $seen = [];
        $lines = [];
        foreach ($foundUrls as $f) {
            $url = trim((string) ($f['url'] ?? ''));
            $key = rtrim(mb_strtolower($url), '/'); // дедуп с нормализацией слэша (site.ru == site.ru/)
            if ($url === '' || isset($seen[$key]) || !preg_match('#^https?://#i', $url)) {
                continue;
            }
            $seen[$key] = true;
            $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
            $name = htmlspecialchars(trim((string) ($f['item_name'] ?? '')), ENT_QUOTES, 'UTF-8');
            $label = $name !== '' ? $name : $safeUrl;
            $lines[] = "{$label} — <a href=\"{$safeUrl}\" style=\"color:{$linkColor};\">{$safeUrl}</a>";
            if (count($seen) >= 6) {
                break;
            }
        }
        if ($lines === []) {
            return '';
        }

        return "<p style=\"margin:0 0 15px 0;{$textStyles}\">{$introHtml}</p>"
            . "<p style=\"margin:0 0 20px 0;{$textStyles}\">" . implode('<br>', $lines) . '</p>';
    }

    // ── Хелперы значений ────────────────────────────────────────────────────

    /**
     * Форматирование количества для письма: «20.000» → «20», «1.500» → «1.5».
     * DB хранит quantity как decimal(_,3), сырой рендер «20.000» поставщик читает
     * как 20 000 — поэтому убираем незначащие нули и дробную часть у целых.
     */
    private function formatQty(mixed $val, string $emptyVal = self::EMPTY_VAL): string
    {
        $s = $this->cleanValue($val, $emptyVal);
        if ($s === $emptyVal || $s === '') {
            return $s;
        }
        $num = str_replace([' ', ','], ['', '.'], trim($s));
        if (!is_numeric($num)) {
            return $s;
        }
        $f = (float) $num;
        if (floor($f) === $f) {
            return (string) (int) $f;
        }

        return rtrim(rtrim(number_format($f, 3, '.', ''), '0'), '.');
    }

    private function cleanValue(mixed $val, string $emptyVal = self::EMPTY_VAL): string
    {
        if ($val === null || $val === '' || $val === 'null') {
            return $emptyVal;
        }
        if (is_string($val) && strtolower(trim($val)) === 'null') {
            return $emptyVal;
        }
        return (string) $val;
    }

    private function hasValue(mixed $val): bool
    {
        if ($val === null || $val === '') {
            return false;
        }
        if (is_string($val)) {
            $trimmed = strtolower(trim($val));
            return $trimmed !== '' && $trimmed !== 'null';
        }
        return true;
    }

    // ── Токены ──────────────────────────────────────────────────────────────

    private function generateTokenSuffix(string $supplierName): string
    {
        $styles = ['alphanumeric', 'numeric', 'alpha', 'mixed', 'short', 'company_based'];
        $style = $styles[random_int(0, count($styles) - 1)];

        $suffix = '';
        switch ($style) {
            case 'alphanumeric':
                $suffix = $this->randomString(3, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ')
                    . $this->randomString(3, '0123456789');
                break;
            case 'numeric':
                $suffix = $this->randomString(6, '0123456789');
                break;
            case 'alpha':
                $suffix = $this->randomString(6, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');
                break;
            case 'mixed':
                for ($i = 0; $i < 3; $i++) {
                    $suffix .= $this->randomString(1, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');
                    $suffix .= $this->randomString(1, '0123456789');
                }
                break;
            case 'short':
                $suffix = $this->randomString(1, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ')
                    . $this->randomString(1, '0123456789')
                    . $this->randomString(1, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');
                break;
            case 'company_based':
                $cleaned = preg_replace('/[\\\\"]/', '', $supplierName) ?? '';
                $words = preg_split('/\s+/', $cleaned, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $abbr = '';
                $limit = min(count($words), 3);
                for ($i = 0; $i < $limit; $i++) {
                    $word = preg_replace('/[^а-яА-ЯёЁa-zA-Z]/u', '', $words[$i]) ?? '';
                    if (mb_strlen($word) > 0) {
                        $abbr .= $this->transliterate(mb_strtoupper(mb_substr($word, 0, 1)));
                    }
                }
                if ($abbr === '') {
                    $abbr = 'SUP';
                }
                $suffix = $abbr . $this->randomString(2, '0123456789');
                break;
        }

        return $suffix;
    }

    private function randomString(int $length, string $charset): string
    {
        $result = '';
        $max = strlen($charset) - 1;
        for ($i = 0; $i < $length; $i++) {
            $result .= $charset[random_int(0, $max)];
        }
        return $result;
    }

    private function transliterate(string $char): string
    {
        $map = [
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'E',
            'Ж' => 'Z', 'З' => 'Z', 'И' => 'I', 'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M',
            'Н' => 'N', 'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U',
            'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C', 'Ч' => 'C', 'Ш' => 'S', 'Щ' => 'S', 'Ы' => 'Y',
            'Э' => 'E', 'Ю' => 'U', 'Я' => 'Y',
        ];
        return $map[$char] ?? $char;
    }

    // ── Используемые поля / обогащение названий ─────────────────────────────

    /**
     * @param array<string,mixed> $config
     * @return array<int,string>
     */
    private function getUsedFields(string $format, array $config): array
    {
        $columns = $config['columns'] ?? [];
        $itemTemplate = $config['item_template'] ?? '';

        $used = [];
        $add = static function (string $f) use (&$used) {
            if (!in_array($f, $used, true)) {
                $used[] = $f;
            }
        };

        if (in_array($format, ['table', 'table_striped', 'table_minimal', 'cards'], true)) {
            foreach ($columns as $col) {
                $add((string) $col);
                if ($col === 'name_with_desc') {
                    $add('name');
                }
            }
        }

        if (in_array($format, ['list', 'list_numbered', 'inline', 'paragraph'], true)) {
            $tpl = $itemTemplate !== '' ? $itemTemplate : $this->getDefaultItemTemplate($format);
            if (preg_match_all('/\{([a-z_]+)\}/i', (string) $tpl, $m)) {
                foreach ($m[1] as $field) {
                    $add(strtolower($field));
                }
            }
            $brandSuffix = $config['brand_suffix_template'] ?? '';
            if (is_string($brandSuffix) && str_contains($brandSuffix, '{brand}')) {
                $add('brand');
            }
        }

        return $used;
    }

    private function getDefaultItemTemplate(string $format): string
    {
        $defaults = [
            'list' => '{name} — {quantity} {unit}',
            'list_numbered' => '{name} — {quantity} {unit}',
            'inline' => '{name} ({quantity} {unit})',
            'paragraph' => '{name} — {quantity} {unit}',
        ];
        return $defaults[$format] ?? '{name} — {quantity} {unit}';
    }

    /**
     * @param array<string,mixed> $item
     * @param array<int,string> $usedFields
     */
    private function enrichItemName(array $item, array $usedFields): string
    {
        $name = $this->cleanValue($item['name'] ?? null, '');
        $brand = $this->cleanValue($item['brand'] ?? null, '');
        $article = $this->cleanValue($item['article'] ?? null, '');

        $nameEmpty = $name === '' || $name === '—' || $name === '-' || strtolower($name) === 'н/д';

        $brandUsed = in_array('brand', $usedFields, true) || in_array('brand_suffix', $usedFields, true);
        $articleUsed = in_array('article', $usedFields, true);

        $hasBrand = $brand !== '' && $brand !== '—' && $brand !== '-' && strtolower($brand) !== 'н/д';
        $hasArticle = $article !== '' && $article !== '—' && $article !== '-' && strtolower($article) !== 'н/д';

        if ($nameEmpty) {
            $parts = [];
            if ($hasBrand) {
                $parts[] = $brand;
            }
            if ($hasArticle) {
                $parts[] = $article;
            }
            if (count($parts) > 0) {
                return implode(' ', $parts);
            }
            return '';
        }

        $parts = [];
        if (!$brandUsed && $hasBrand) {
            $parts[] = $brand;
        }
        if (!$articleUsed && $hasArticle) {
            $parts[] = $article;
        }
        if (count($parts) === 0) {
            return $name;
        }
        return trim($name . ' ' . implode(' ', $parts));
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @param array<string,mixed> $config
     * @return array<int,array<string,mixed>>
     */
    private function prepareItemsForRender(array $items, string $format, array $config): array
    {
        $usedFields = $this->getUsedFields($format, $config);
        return array_map(function ($item) use ($usedFields) {
            $item = (array) $item;
            $item['_original_name'] = $item['name'] ?? null;
            $item['name'] = $this->enrichItemName($item, $usedFields);
            return $item;
        }, $items);
    }

    // ── Рендер товаров ──────────────────────────────────────────────────────

    /**
     * @param array<int,array<string,mixed>> $items
     * @param array<string,mixed> $config
     */
    private function renderItems(array $items, string $format, array $config): string
    {
        $style = $config['style'] ?? [];
        $labels = $config['column_labels'] ?? [];
        $columns = $config['columns'] ?? ['index', 'name', 'brand', 'article', 'quantity'];
        $emptyVal = $config['empty_value'] ?? self::EMPTY_VAL;

        $enriched = $this->prepareItemsForRender($items, $format, $config);

        switch ($format) {
            case 'table':
            case 'table_striped':
                return $this->renderTable($enriched, $config, $style, $labels, $columns, $emptyVal, true);
            case 'table_minimal':
                return $this->renderTable($enriched, $config, $style, $labels, $columns, $emptyVal, false);
            case 'list':
                return $this->renderList($enriched, $config, $style, 'bullet');
            case 'list_numbered':
                return $this->renderList($enriched, $config, $style, 'numbered');
            case 'cards':
                return $this->renderCards($enriched, $config, $style, $labels, $emptyVal);
            case 'inline':
                return $this->renderInline($enriched, $config, $style);
            case 'paragraph':
                return $this->renderParagraph($enriched, $config, $style);
            default:
                return $this->renderTable($enriched, $config, $style, $labels, $columns, $emptyVal, true);
        }
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @param array<string,mixed> $cfg
     * @param array<string,mixed> $style
     * @param array<string,mixed> $labels
     * @param array<int,string> $columns
     */
    private function renderTable(array $items, array $cfg, array $style, array $labels, array $columns, string $emptyVal, bool $showBorders): string
    {
        // Плоская таблица (деливерабилити): без маркетинговой окраски — нет цветной
        // шапки/полосок/внешних рамок. Только тонкие серые линии-разделители, как в
        // обычном деловом письме. Цвета из config игнорируем намеренно (spam-фильтры
        // не любят «баннерные» HTML-таблицы; чистый текст доходит лучше).
        $fontSize = $style['font_size'] ?? '10pt';

        $html = "<table style=\"width:100%;border-collapse:collapse;margin:16px 0;font-size:{$fontSize};\">";

        if (($cfg['show_header'] ?? null) !== false) {
            $html .= '<thead><tr>';
            foreach ($columns as $col) {
                $label = $labels[$col] ?? $this->getDefaultLabel((string) $col);
                $html .= "<th style=\"padding:6px 8px;text-align:left;font-weight:bold;border-bottom:2px solid #999;\">{$label}</th>";
            }
            $html .= '</tr></thead>';
        }

        $html .= '<tbody>';
        foreach ($items as $idx => $item) {
            $html .= '<tr>';
            foreach ($columns as $col) {
                $value = $this->getCellValue($item, (string) $col, $idx, $cfg, $style, $emptyVal);
                $html .= "<td style=\"padding:6px 8px;border-bottom:1px solid #ddd;\">{$value}</td>";
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @param array<string,mixed> $cfg
     * @param array<string,mixed> $style
     */
    private function renderList(array $items, array $cfg, array $style, string $listStyle): string
    {
        $itemTemplate = $cfg['item_template'] ?? '{name} — {quantity} {unit}';
        $brandSuffix = $cfg['brand_suffix_template'] ?? '({brand})';
        $textColor = $style['text_color'] ?? '#333';
        $fontSize = $style['font_size'] ?? '10pt';
        $nameColor = $style['name_color'] ?? $textColor;
        $boldName = $style['bold_name'] ?? false;
        $boldQuantity = $style['bold_quantity'] ?? false;

        $html = "<div style=\"margin:20px 0;color:{$textColor};font-size:{$fontSize};\">";
        $html .= $listStyle === 'numbered' ? '<ol style="padding-left:25px;margin:0;">' : '<ul style="padding-left:25px;margin:0;">';

        foreach ($items as $idx => $item) {
            $line = (string) $itemTemplate;

            $name = $this->cleanValue($item['name'] ?? null, '');
            if ($boldName) {
                $displayName = $nameColor !== $textColor ? "<strong style=\"color:{$nameColor};\">{$name}</strong>" : "<strong>{$name}</strong>";
            } else {
                $displayName = $nameColor !== $textColor ? "<span style=\"color:{$nameColor};\">{$name}</span>" : $name;
            }

            $qty = $this->formatQty($item['quantity'] ?? null, '');
            $displayQty = $boldQuantity ? "<strong>{$qty}</strong>" : $qty;

            $line = $this->replaceFirst($line, '{index}', (string) ($idx + 1));
            $line = $this->replaceFirst($line, '{name}', $displayName);
            $line = $this->replaceFirst($line, '{quantity}', $displayQty);
            $line = $this->replaceFirst($line, '{unit}', $this->cleanValue($item['unit'] ?? null, 'шт'));
            $line = $this->replaceFirst($line, '{brand}', $this->cleanValue($item['brand'] ?? null, ''));
            $line = $this->replaceFirst($line, '{article}', $this->cleanValue($item['article'] ?? null, ''));

            if ($this->hasValue($item['brand'] ?? null)) {
                $line = $this->replaceFirst($line, '{brand_suffix}', $this->replaceFirst((string) $brandSuffix, '{brand}', (string) ($item['brand'] ?? '')));
            } else {
                $line = $this->replaceFirst($line, '{brand_suffix}', '');
            }

            $line = preg_replace('/\{[a-z_]+\}/i', '', $line) ?? $line;
            $line = trim((string) preg_replace('/\s+/', ' ', $line));

            $html .= "<li style=\"margin:8px 0;\">{$line}</li>";
        }

        $html .= $listStyle === 'numbered' ? '</ol>' : '</ul>';
        $html .= '</div>';

        return $html;
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @param array<string,mixed> $cfg
     * @param array<string,mixed> $style
     * @param array<string,mixed> $labels
     */
    private function renderCards(array $items, array $cfg, array $style, array $labels, string $emptyVal): string
    {
        // Плоские «карточки» (деливерабилити): без цветной рамки/радиуса/цветного
        // заголовка — обычный текстовый блок на позицию (жирное название + строки
        // без цветных лейблов). Маркетинговую окраску из config игнорируем.
        $fontSize = $style['font_size'] ?? '10pt';
        $columns = $cfg['columns'] ?? ['name', 'brand', 'article', 'quantity'];

        $html = "<div style=\"margin:16px 0;font-size:{$fontSize};\">";

        foreach ($items as $idx => $item) {
            $html .= '<div style="margin:12px 0;">';
            $itemName = $this->cleanValue($item['name'] ?? null, 'Товар');
            $html .= "<p style=\"margin:0 0 4px 0;font-weight:bold;\">" . ($idx + 1) . ". {$itemName}</p>";

            foreach ($columns as $col) {
                if ($col === 'name' || $col === 'index') {
                    continue;
                }
                $label = $labels[$col] ?? $this->getDefaultLabel((string) $col);

                if ($col === 'quantity') {
                    $val = '<strong>' . $this->formatQty($item['quantity'] ?? null, '1') . ' ' . $this->cleanValue($item['unit'] ?? null, 'шт') . '</strong>';
                    $html .= "<p style=\"margin:2px 0 2px 14px;\">{$label}: {$val}</p>";
                } elseif ($col === 'description') {
                    if ($this->hasValue($item['description'] ?? null)) {
                        $html .= "<p style=\"margin:2px 0 2px 14px;\">{$label}: " . $this->cleanValue($item['description'] ?? null, $emptyVal) . '</p>';
                    }
                } else {
                    if ($this->hasValue($item[$col] ?? null)) {
                        $html .= "<p style=\"margin:2px 0 2px 14px;\">{$label}: " . $this->cleanValue($item[$col] ?? null, $emptyVal) . '</p>';
                    }
                }
            }

            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @param array<string,mixed> $cfg
     * @param array<string,mixed> $style
     */
    private function renderInline(array $items, array $cfg, array $style): string
    {
        $itemTemplate = $cfg['item_template'] ?? '{name} ({quantity} {unit})';
        $separator = $cfg['separator'] ?? '; ';
        $prefix = $cfg['prefix'] ?? '';
        $suffix = $cfg['suffix'] ?? '.';
        $textColor = $style['text_color'] ?? '#333';
        $fontSize = $style['font_size'] ?? '10pt';
        $nameColor = $style['name_color'] ?? $textColor;
        $boldName = $style['bold_name'] ?? false;
        $boldQuantity = $style['bold_quantity'] ?? false;

        $parts = [];
        foreach ($items as $idx => $item) {
            $line = (string) $itemTemplate;

            $name = $this->cleanValue($item['name'] ?? null, '');
            if ($boldName) {
                $displayName = $nameColor !== $textColor ? "<strong style=\"color:{$nameColor};\">{$name}</strong>" : "<strong>{$name}</strong>";
            } else {
                $displayName = $nameColor !== $textColor ? "<span style=\"color:{$nameColor};\">{$name}</span>" : $name;
            }

            $qtyVal = $this->formatQty($item['quantity'] ?? null, '');
            $unitVal = $this->cleanValue($item['unit'] ?? null, 'шт');
            $displayQty = $boldQuantity ? "<strong>{$qtyVal}</strong>" : $qtyVal;
            $displayUnit = $boldQuantity ? "<strong>{$unitVal}</strong>" : $unitVal;

            $line = $this->replaceFirst($line, '{index}', (string) ($idx + 1));
            $line = $this->replaceFirst($line, '{name}', $displayName);
            $line = $this->replaceFirst($line, '{quantity}', $displayQty);
            $line = $this->replaceFirst($line, '{unit}', $displayUnit);
            $line = $this->replaceFirst($line, '{brand}', $this->cleanValue($item['brand'] ?? null, ''));
            $line = $this->replaceFirst($line, '{article}', $this->cleanValue($item['article'] ?? null, ''));

            $line = preg_replace('/\{[a-z_]+\}/i', '', $line) ?? $line;
            $line = preg_replace('/,\s*,/', ',', $line) ?? $line;
            $line = preg_replace('/,\s*$/', '', $line) ?? $line;
            $line = preg_replace('/^\s*,/', '', $line) ?? $line;
            $line = trim((string) preg_replace('/\s+/', ' ', $line));

            $parts[] = $line;
        }

        if ($separator === '\\n' || $separator === "\n") {
            $text = $prefix . implode('<br>', $parts) . $suffix;
        } else {
            $text = $prefix . implode((string) $separator, $parts) . $suffix;
        }

        return "<div style=\"margin:20px 0;color:{$textColor};font-size:{$fontSize};line-height:1.8;\">{$text}</div>";
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @param array<string,mixed> $cfg
     * @param array<string,mixed> $style
     */
    private function renderParagraph(array $items, array $cfg, array $style): string
    {
        $introText = $cfg['intro_text'] ?? '';
        $itemTemplate = $cfg['item_template'] ?? '{name} — {quantity} {unit}';
        $separator = $cfg['separator'] ?? ', ';
        $lastSeparator = $cfg['last_separator'] ?? ' и ';
        $suffix = $cfg['suffix'] ?? '.';
        $textColor = $style['text_color'] ?? '#333';
        $fontSize = $style['font_size'] ?? '10pt';
        $nameColor = $style['name_color'] ?? $textColor;
        $boldName = $style['bold_name'] ?? false;

        $parts = [];
        foreach ($items as $idx => $item) {
            $line = (string) $itemTemplate;

            $name = $this->cleanValue($item['name'] ?? null, '');
            if ($boldName) {
                $displayName = $nameColor !== $textColor ? "<strong style=\"color:{$nameColor};\">{$name}</strong>" : "<strong>{$name}</strong>";
            } else {
                $displayName = $name;
            }

            $line = $this->replaceFirst($line, '{index}', (string) ($idx + 1));
            $line = $this->replaceFirst($line, '{name}', $displayName);
            $line = $this->replaceFirst($line, '{quantity}', $this->formatQty($item['quantity'] ?? null, ''));
            $line = $this->replaceFirst($line, '{unit}', $this->cleanValue($item['unit'] ?? null, 'шт'));
            $line = $this->replaceFirst($line, '{brand}', $this->cleanValue($item['brand'] ?? null, ''));

            $line = preg_replace('/\{[a-z_]+\}/i', '', $line) ?? $line;

            $parts[] = trim($line);
        }

        $text = $introText ? $introText . ' ' : '';

        $count = count($parts);
        if ($count === 1) {
            $text .= $parts[0];
        } elseif ($count === 2) {
            $text .= implode((string) $lastSeparator, $parts);
        } elseif ($count > 0) {
            $text .= implode((string) $separator, array_slice($parts, 0, -1)) . $lastSeparator . $parts[$count - 1];
        }

        $text .= $suffix;

        return "<p style=\"margin:20px 0;color:{$textColor};font-size:{$fontSize};line-height:1.8;\">{$text}</p>";
    }

    /**
     * @param array<string,mixed> $item
     * @param array<string,mixed> $cfg
     * @param array<string,mixed> $style
     */
    private function getCellValue(array $item, string $col, int $idx, array $cfg, array $style, string $emptyVal): string
    {
        $boldName = ($style['bold_name'] ?? null) !== false;       // дефолт true для таблиц
        $boldQuantity = ($style['bold_quantity'] ?? null) !== false; // дефолт true для таблиц
        $nameColor = $style['name_color'] ?? '';
        $qtyFormat = $cfg['quantity_format'] ?? '{value} {unit}';

        switch ($col) {
            case 'index':
                return (string) ($idx + 1);
            case 'name':
                $name = $this->cleanValue($item['name'] ?? null, $emptyVal);
                if ($boldName) {
                    return $nameColor ? "<strong style=\"color:{$nameColor};\">{$name}</strong>" : "<strong>{$name}</strong>";
                }
                return $nameColor ? "<span style=\"color:{$nameColor};\">{$name}</span>" : $name;
            case 'name_with_desc':
                $nameWithDesc = $this->cleanValue($item['name'] ?? null, $emptyVal);
                if ($this->hasValue($item['description'] ?? null)) {
                    $nameWithDesc .= "<br><span style=\"font-size:0.9em;color:#666;\">" . ($item['description'] ?? '') . '</span>';
                }
                if ($boldName) {
                    return $nameColor ? "<strong style=\"color:{$nameColor};\">{$nameWithDesc}</strong>" : "<strong>{$nameWithDesc}</strong>";
                }
                return $nameColor ? "<span style=\"color:{$nameColor};\">{$nameWithDesc}</span>" : $nameWithDesc;
            case 'brand':
                return $this->cleanValue($item['brand'] ?? null, $emptyVal);
            case 'article':
                return $this->cleanValue($item['article'] ?? null, $emptyVal);
            case 'quantity':
                $qty = $this->replaceFirst((string) $qtyFormat, '{value}', $this->formatQty($item['quantity'] ?? null, ''));
                $qty = $this->replaceFirst($qty, '{unit}', $this->cleanValue($item['unit'] ?? null, 'шт'));
                return $boldQuantity ? "<strong>{$qty}</strong>" : $qty;
            case 'unit':
                return $this->cleanValue($item['unit'] ?? null, 'шт');
            case 'description':
                return $this->cleanValue($item['description'] ?? null, $emptyVal);
            case 'price_placeholder':
            case 'term_placeholder':
                return '';
            default:
                return $this->cleanValue($item[$col] ?? null, $emptyVal);
        }
    }

    private function getDefaultLabel(string $col): string
    {
        $defaults = [
            'index' => '№',
            'name' => 'Наименование',
            'name_with_desc' => 'Наименование',
            'brand' => 'Марка',
            'article' => 'Артикул',
            'quantity' => 'Кол-во',
            'unit' => 'Ед. изм.',
            'description' => 'Описание',
            'price_placeholder' => 'Цена',
            'term_placeholder' => 'Срок',
        ];
        return $defaults[$col] ?? $col;
    }

    // ── Подпись ─────────────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $senderData
     * @param array<string,mixed>|null $organization
     * @param array<string,mixed> $style
     */
    private function renderSignature(string $format, array $senderData, ?array $organization, array $style): string
    {
        $fontSize = $style['font_size'] ?? '10pt';
        $textColor = $style['text_color'] ?? '#333';
        $italic = $style['italic'] ?? false;
        $italicStyle = $italic ? 'font-style:italic;' : '';

        $greetingText = array_key_exists('greeting', $style) ? $style['greeting'] : 'С уважением,';
        $greetingColor = $style['greeting_color'] ?? $textColor;

        $nameColor = $style['name_color'] ?? $textColor;
        $nameSize = $style['name_size'] ?? $fontSize;
        $boldName = $style['bold_name'] ?? false;

        $separator = $style['separator'] ?? '';
        $positionBeforeGreeting = $style['position_before_greeting'] ?? false;
        $positionAfterName = $style['position_after_name'] ?? false;

        $linkColor = $style['link_color'] ?? $textColor;

        $fullName = ($senderData['sender_full_name'] ?? null) ?: (($senderData['sender_name'] ?? null) ?: 'Имя не указано');
        $phone = $senderData['phone'] ?? null;
        $email = $senderData['email'] ?? null;
        $position = $senderData['position'] ?? null;

        $html = "<div style=\"margin-top:30px;font-size:{$fontSize};color:{$textColor};{$italicStyle}\">";

        if ($separator) {
            $html .= "<p style=\"margin:0 0 10px 0;\">{$separator}</p>";
        }

        if ($positionBeforeGreeting && $position && $format !== 'name_only') {
            $html .= "<p style=\"margin:0;\">{$position}</p>";
        }

        if ($greetingText && $greetingText !== '') {
            $html .= "<p style=\"margin:5px 0;color:{$greetingColor};\">{$greetingText}</p>";
        }

        if (!$positionBeforeGreeting && !$positionAfterName && $position && $format !== 'name_only') {
            $html .= "<p style=\"margin:0;\">{$position}</p>";
        }

        $html .= '<p style="margin:5px 0 0 0;">';
        if ($boldName) {
            $html .= "<strong style=\"color:{$nameColor};font-size:{$nameSize};\">{$fullName}</strong>";
        } else {
            $html .= "<span style=\"color:{$nameColor};font-size:{$nameSize};\">{$fullName}</span>";
        }
        $html .= '</p>';

        if ($positionAfterName && $position && $format !== 'name_only') {
            $html .= "<p style=\"margin:0;\">{$position}</p>";
        }

        $contacts = '';
        switch ($format) {
            case 'name_only':
                break;
            case 'name_organization':
                if ($organization) {
                    $contacts .= "<p style=\"margin:0;\">{$organization['name']}</p>";
                }
                break;
            case 'name_org_phone':
                if ($organization) {
                    $contacts .= "<p style=\"margin:0;\">{$organization['name']}</p>";
                }
                if ($phone) {
                    $contacts .= "<p style=\"margin:0;\">{$phone}</p>";
                }
                break;
            case 'name_org_contacts':
                if ($organization) {
                    $contacts .= "<p style=\"margin:0;\">{$organization['name']}</p>";
                }
                if ($organization && !empty($organization['actual_address'])) {
                    $contacts .= "<p style=\"margin:0;\">{$organization['actual_address']}</p>";
                }
                if ($phone) {
                    $contacts .= "<p style=\"margin:0;\">{$phone}</p>";
                }
                if ($email) {
                    $contacts .= "<p style=\"margin:0;\"><a href=\"mailto:{$email}\" style=\"color:{$linkColor};\">{$email}</a></p>";
                }
                if ($organization && !empty($organization['website'])) {
                    $contacts .= "<p style=\"margin:0;\"><a href=\"{$organization['website']}\" style=\"color:{$linkColor};\">{$organization['website']}</a></p>";
                }
                break;
            case 'full':
                if ($organization) {
                    $contacts .= "<p style=\"margin:0;\">{$organization['name']}</p>";
                    if (!empty($organization['actual_address'])) {
                        $contacts .= "<p style=\"margin:0;\">{$organization['actual_address']}</p>";
                    }
                    if (!empty($organization['inn'])) {
                        $contacts .= "<p style=\"margin:0;\">ИНН: {$organization['inn']}</p>";
                    }
                }
                if ($phone) {
                    $contacts .= "<p style=\"margin:0;\">{$phone}</p>";
                }
                if ($email) {
                    $contacts .= "<p style=\"margin:0;\"><a href=\"mailto:{$email}\" style=\"color:{$linkColor};\">{$email}</a></p>";
                }
                break;
        }

        $html .= $contacts;
        $html .= '</div>';
        return $html;
    }

    // ── Рендер блоков ───────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $block
     * @param array<string,mixed> $context
     * @param array<string,mixed> $globalStyle
     */
    private function renderBlock(array $block, array $context, array $globalStyle): string
    {
        $items = $context['items'];
        $template = $context['template'];
        $sender = $context['sender'];
        $organization = $context['organization'];
        $token = $context['token'];
        $aiContent = $context['aiContent'];
        $displayConfig = $context['displayConfig'];

        $blockStyle = $block['style'] ?? [];
        $style = array_merge($globalStyle, is_array($blockStyle) ? $blockStyle : []);

        $fontSize = $style['font_size'] ?? '10pt';
        $textColor = $style['text_color'] ?? '#333';
        $italic = $style['italic'] ?? false;
        $bold = $style['bold'] ?? false;
        $headerColor = $style['header_bg'] ?? '#2c5aa0';

        $textStyles = "font-size:{$fontSize};color:{$textColor};";
        if ($italic) {
            $textStyles .= 'font-style:italic;';
        }
        if ($bold) {
            $textStyles .= 'font-weight:bold;';
        }

        $type = $block['type'] ?? '';

        switch ($type) {
            case 'static_header':
                // Плоский заголовок (деливерабилити): без центрированного цветного
                // баннера-<h1> — обычная жирная строка, как в деловом письме.
                return "<div style=\"margin:0 0 14px 0;\"><p style=\"margin:0;font-weight:bold;{$textStyles}\">" . ($block['content'] ?? '') . '</p></div>';

            case 'ai_greeting':
                $greeting = $aiContent['greeting'] ?: $this->getFallbackGreeting($block['tone'] ?? null);
                return "<p style=\"margin:0 0 15px 0;{$textStyles}\">{$greeting}</p>";

            case 'ai_introduction':
                $intro = $aiContent['introduction'] ?: 'Прошу вас предоставить коммерческое предложение на следующие товары:';
                return "<p style=\"margin:0 0 20px 0;{$textStyles}line-height:1.6;\">{$intro}</p>";

            case 'items_display':
                $format = $template['items_format'] ?? ($block['format'] ?? 'table');
                return $this->renderItems($items, (string) $format, $displayConfig);

            case 'static_text':
                return "<div style=\"margin:15px 0;{$textStyles}white-space:pre-line;\">" . ($block['content'] ?? '') . '</div>';

            case 'requirements_box':
                // Плоско: без цветной плашки и левой цветной полосы — обычный абзац.
                $boxContent = (string) ($block['content'] ?? '');
                $boxContent = str_replace("\n", '<br>', $boxContent);
                return "<div style=\"margin:16px 0;{$textStyles}\">{$boxContent}</div>";

            case 'ai_closing':
                $closing = $aiContent['closing'] ?: 'Буду признателен за оперативный ответ.';
                return "<p style=\"margin:20px 0;{$textStyles}line-height:1.6;\">{$closing}</p>";

            case 'signature':
                $sigStyle = array_merge($globalStyle, is_array($block['style'] ?? null) ? $block['style'] : []);
                $sigFormat = $block['format'] ?? ($template['signature_format'] ?? 'name_only');
                return $this->renderSignature((string) $sigFormat, $sender, $organization, $sigStyle);

            case 'company_footer':
                if (!$organization) {
                    return '';
                }
                $innLine = !empty($organization['inn']) ? "ИНН: {$organization['inn']}" : '';
                $legal = $organization['legal_address'] ?? '';
                // Плоско: без серой плашки/центрирования — обычная мелкая подпись-футер.
                return "<div style=\"margin-top:16px;font-size:9pt;color:#666;\">\n        <strong>{$organization['name']}</strong><br>\n        {$innLine}<br>\n        {$legal}\n      </div>";

            case 'token':
                return "<div style=\"margin-top:20px;\"><p style=\"margin:0;color:#ffffff;font-size:1px;line-height:1px;mso-hide:all;\">Ref: {$token}</p></div>";

            default:
                return '';
        }
    }

    private function getFallbackGreeting(?string $tone): string
    {
        $greetings = [
            'formal' => 'Здравствуйте,',
            'friendly' => 'Добрый день!',
            'neutral' => 'Добрый день,',
            'brief' => 'Добрый день,',
        ];
        return $greetings[$tone] ?? 'Добрый день,';
    }

    // ── Утилиты ─────────────────────────────────────────────────────────────

    /**
     * Аналог JS String.replace(string, string) — заменяет ТОЛЬКО первое вхождение.
     */
    private function replaceFirst(string $subject, string $search, string $replace): string
    {
        if ($search === '') {
            return $subject;
        }
        $pos = strpos($subject, $search);
        if ($pos === false) {
            return $subject;
        }
        return substr_replace($subject, $replace, $pos, strlen($search));
    }

    /**
     * Декодирует значение, которое может быть JSON-строкой или уже массивом.
     *
     * @return array<string,mixed>
     */
    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }
}
