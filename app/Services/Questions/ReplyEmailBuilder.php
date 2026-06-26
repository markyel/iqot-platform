<?php

namespace App\Services\Questions;

/**
 * Сборка письма-ответа поставщику — порт n8n «Build Reply Email» (v2.3).
 *
 * Рендерит HTML по блокам шаблона email_templates (signature по форматам
 * name_only…full, скрытый 1px-белый токен, items_display = текст ответа AI),
 * цитирует исходное письмо (gmail_quote), формирует Re:-тему, references_header
 * и plain-text версию.
 *
 * Возвращает массив полей для INSERT в outgoing_replies (status='pending').
 */
class ReplyEmailBuilder
{
    /**
     * @param array<string,mixed> $context результат классификатора + данные отправителя
     * @param object|null $originalMessage последнее входящее письмо беседы
     * @param object|null $template строка email_templates (или null)
     * @return array<string,mixed>
     */
    public function build(array $context, ?object $originalMessage, ?object $template): array
    {
        $template ??= (object) [];

        $displayConfig = $this->decodeJson($template->items_display_config ?? null);
        $globalStyle = is_array($displayConfig['style'] ?? null) ? $displayConfig['style'] : [];

        $colors = $this->getColorScheme((string) ($template->style_preset ?? 'professional'));
        $blocks = $this->decodeJson($template->blocks ?? null);

        $answerContext = [
            'colors' => $colors,
            'sender' => [
                'sender_full_name' => $context['sender_full_name'] ?? null,
                'phone' => $context['sender_phone'] ?? null,
                'email' => $context['sender_email'] ?? null,
                'position' => $context['sender_position'] ?? null,
            ],
            'organization' => $context['organization_name'] ?? null,
            'token' => $context['tracking_token'] ?? null,
            'answerText' => (string) ($context['answer_text'] ?? ''),
            'greeting' => (string) ($context['sender_greeting'] ?? 'Здравствуйте'),
        ];

        $contentBlocks = [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            $html = $this->renderAnswerBlock($block, $answerContext, $globalStyle);
            if ($html !== '') {
                $contentBlocks[] = $html;
            }
        }

        $quoted = $this->renderQuotedOriginal($originalMessage);
        $contentHtml = implode('', $contentBlocks);

        $emailHtml = "<!DOCTYPE html>\n<html>\n<head><meta charset=\"UTF-8\"></head>\n"
            . "<body style=\"font-family:Arial,sans-serif;line-height:1.6;color:#333;margin:0;padding:0;\">\n"
            . "<div dir=\"ltr\" style=\"padding:10px;\">\n{$contentHtml}\n</div>\n<br>\n"
            . "<div class=\"gmail_quote gmail_quote_container\">\n{$quoted}\n</div>\n</body>\n</html>";

        $origSubject = (string) ($originalMessage->subject ?? '');
        $subject = 'Re: ' . preg_replace('/^Re:\s*/i', '', $origSubject);

        $origMessageId = (string) ($originalMessage->message_id ?? '');
        $origReferences = (string) ($originalMessage->references_header ?? '');
        $references = $origReferences !== ''
            ? trim($origReferences . ' ' . $origMessageId)
            : $origMessageId;

        $bodyText = $this->buildPlainText($context, $originalMessage);

        return [
            'question_id' => (int) ($context['question_id'] ?? 0),
            'conversation_id' => (int) ($context['conversation_id'] ?? 0),
            'supplier_question_id' => (int) ($context['question_id'] ?? 0),
            'sender_id' => (int) ($context['sender_id'] ?? 0),
            'supplier_id' => (int) ($context['supplier_id'] ?? 0),
            'from_email' => (string) ($context['sender_email'] ?? ''),
            'to_email' => (string) ($originalMessage->from_email ?? ''),
            'subject' => $subject,
            'body_text' => $bodyText,
            'body_html' => $emailHtml,
            'in_reply_to' => $origMessageId,
            'references_header' => $references,
            'original_reply_id' => $context['original_reply_id'] ?? null,
            'has_files_to_copy' => (bool) ($context['has_files_to_copy'] ?? false),
        ];
    }

    /**
     * @return array<string,array<string,string>>
     */
    private function getColorScheme(string $stylePreset): array
    {
        $schemes = [
            'professional' => ['header' => '#2c5aa0', 'headerBorder' => '#1e3a5f', 'headerText' => '#ffffff', 'row1' => '#ffffff', 'row2' => '#f5f7fa', 'border' => '#ddd'],
            'friendly' => ['header' => '#27ae60', 'headerBorder' => '#1e8449', 'headerText' => '#ffffff', 'row1' => '#ffffff', 'row2' => '#e8f8f5', 'border' => '#d5f4e6'],
            'minimal' => ['header' => '#5a5a5a', 'headerBorder' => '#3a3a3a', 'headerText' => '#ffffff', 'row1' => '#ffffff', 'row2' => '#f5f5f5', 'border' => '#e0e0e0'],
            'corporate' => ['header' => '#1a3a5a', 'headerBorder' => '#0f2438', 'headerText' => '#ffffff', 'row1' => '#ffffff', 'row2' => '#e8f0f7', 'border' => '#cce0f0'],
            'modern' => ['header' => '#8e44ad', 'headerBorder' => '#6c3483', 'headerText' => '#ffffff', 'row1' => '#ffffff', 'row2' => '#f4ecf7', 'border' => '#e8daef'],
        ];

        return $schemes[$stylePreset] ?? $schemes['professional'];
    }

    /**
     * Порт renderAnswerBlock: для триажа значимы ai_greeting, items_display
     * (текст ответа), signature, token. Остальные блоки рендерятся пустыми, как
     * в исходном узле.
     *
     * @param array<string,mixed> $block
     * @param array<string,mixed> $answerContext
     * @param array<string,mixed> $globalStyle
     */
    private function renderAnswerBlock(array $block, array $answerContext, array $globalStyle): string
    {
        $colors = $answerContext['colors'];
        $blockStyle = is_array($block['style'] ?? null) ? $block['style'] : [];
        $style = array_merge($globalStyle, $blockStyle);

        $fontSize = (string) ($style['font_size'] ?? '10pt');
        $textColor = (string) ($style['text_color'] ?? '#333');
        $italic = (bool) ($style['italic'] ?? false);
        $bold = (bool) ($style['bold'] ?? false);

        $textStyles = "font-size:{$fontSize};color:{$textColor};";
        if ($italic) {
            $textStyles .= 'font-style:italic;';
        }
        if ($bold) {
            $textStyles .= 'font-weight:bold;';
        }

        $type = (string) ($block['type'] ?? '');

        switch ($type) {
            case 'ai_greeting':
                $greeting = (string) $answerContext['greeting'];
                return "<div style=\"margin-bottom:20px;{$textStyles}\">{$greeting},</div>";

            case 'items_display':
                $answerText = nl2br(htmlspecialchars((string) $answerContext['answerText'], ENT_QUOTES, 'UTF-8'), false);
                return "<div style=\"margin-bottom:20px;{$textStyles}white-space:pre-line;\">{$answerText}</div>";

            case 'ai_introduction':
            case 'ai_closing':
            case 'static_text':
            case 'requirements_box':
                return '';

            case 'signature':
                $sigStyle = array_merge($globalStyle, $blockStyle);
                return $this->renderSignature(
                    (string) ($block['format'] ?? 'name_only'),
                    $answerContext['sender'],
                    $answerContext['organization'],
                    $sigStyle,
                );

            case 'token':
                $token = htmlspecialchars((string) ($answerContext['token'] ?? ''), ENT_QUOTES, 'UTF-8');
                return "<div style=\"margin-top:20px;\"><p style=\"margin:0;color:#ffffff;font-size:1px;line-height:1px;mso-hide:all;\">Ref: {$token}</p></div>";

            default:
                return '';
        }

        // colors intentionally unused for these block types (parity with n8n)
    }

    /**
     * Порт renderSignature (v3.1): форматы name_only…full, position_before_greeting /
     * position_after_name, разделитель, ссылки.
     *
     * @param array<string,mixed> $sender
     * @param string|array<string,mixed>|null $organization
     * @param array<string,mixed> $style
     */
    private function renderSignature(string $format, array $sender, string|array|null $organization, array $style): string
    {
        $fontSize = (string) ($style['font_size'] ?? '10pt');
        $textColor = (string) ($style['text_color'] ?? '#333');
        $italic = (bool) ($style['italic'] ?? false);
        $italicStyle = $italic ? 'font-style:italic;' : '';

        $greetingText = array_key_exists('greeting', $style) ? (string) $style['greeting'] : 'С уважением,';
        $greetingColor = (string) ($style['greeting_color'] ?? $textColor);

        $nameColor = (string) ($style['name_color'] ?? $textColor);
        $nameSize = (string) ($style['name_size'] ?? $fontSize);
        $boldName = (bool) ($style['bold_name'] ?? false);

        $separator = (string) ($style['separator'] ?? '');
        $positionBeforeGreeting = (bool) ($style['position_before_greeting'] ?? false);
        $positionAfterName = (bool) ($style['position_after_name'] ?? false);
        $linkColor = (string) ($style['link_color'] ?? $textColor);

        $fullName = (string) ($sender['sender_full_name'] ?? 'Имя не указано');
        $phone = $sender['phone'] ?? null;
        $email = $sender['email'] ?? null;
        $position = $sender['position'] ?? null;

        $org = is_string($organization) ? ['name' => $organization] : (is_array($organization) ? $organization : []);

        $html = "<div style=\"margin-top:30px;font-size:{$fontSize};color:{$textColor};{$italicStyle}\">";

        if ($separator !== '') {
            $html .= "<p style=\"margin:0 0 10px 0;\">{$separator}</p>";
        }

        if ($positionBeforeGreeting && $position && $format !== 'name_only') {
            $html .= "<p style=\"margin:0;\">{$position}</p>";
        }

        if ($greetingText !== '') {
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

        $html .= $this->renderSignatureContacts($format, $org, $phone, $email, $linkColor);
        $html .= '</div>';

        return $html;
    }

    /**
     * @param array<string,mixed> $org
     */
    private function renderSignatureContacts(string $format, array $org, mixed $phone, mixed $email, string $linkColor): string
    {
        $name = $org['name'] ?? null;
        $address = $org['actual_address'] ?? null;
        $inn = $org['inn'] ?? null;
        $website = $org['website'] ?? null;

        $contacts = '';

        $p = static fn (string $inner): string => "<p style=\"margin:0;\">{$inner}</p>";
        $mail = static fn (string $e): string => "<p style=\"margin:0;\"><a href=\"mailto:{$e}\" style=\"color:{$linkColor};\">{$e}</a></p>";

        switch ($format) {
            case 'name_only':
                break;
            case 'name_organization':
                if ($name) {
                    $contacts .= $p((string) $name);
                }
                break;
            case 'name_org_phone':
                if ($name) {
                    $contacts .= $p((string) $name);
                }
                if ($phone) {
                    $contacts .= $p((string) $phone);
                }
                break;
            case 'name_org_contacts':
                if ($name) {
                    $contacts .= $p((string) $name);
                }
                if ($address) {
                    $contacts .= $p((string) $address);
                }
                if ($phone) {
                    $contacts .= $p((string) $phone);
                }
                if ($email) {
                    $contacts .= $mail((string) $email);
                }
                if ($website) {
                    $contacts .= "<p style=\"margin:0;\"><a href=\"http://{$website}\" style=\"color:{$linkColor};\">{$website}</a></p>";
                }
                break;
            case 'full':
                if ($name) {
                    $contacts .= $p((string) $name);
                }
                if ($address) {
                    $contacts .= $p((string) $address);
                }
                if ($inn) {
                    $contacts .= $p('ИНН: ' . $inn);
                }
                if ($phone) {
                    $contacts .= $p((string) $phone);
                }
                if ($email) {
                    $contacts .= $mail((string) $email);
                }
                break;
        }

        return $contacts;
    }

    private function renderQuotedOriginal(?object $originalMessage): string
    {
        if ($originalMessage === null || empty($originalMessage->body_text)) {
            return '';
        }

        $date = $this->formatRuDate($originalMessage->received_at ?? null);
        $fromEmail = (string) ($originalMessage->from_email ?? 'unknown');
        $quotedText = str_replace("\n", "<br>\n", (string) $originalMessage->body_text);

        return "<div dir=\"ltr\" class=\"gmail_attr\">{$date}, &lt;<a href=\"mailto:{$fromEmail}\">{$fromEmail}</a>&gt;:<br></div>\n"
            . "<blockquote class=\"gmail_quote\" style=\"margin:0px 0px 0px 0.8ex;border-left:1px solid rgb(204,204,204);padding-left:1ex;\">\n"
            . "{$quotedText}\n</blockquote>";
    }

    /**
     * @param array<string,mixed> $context
     */
    private function buildPlainText(array $context, ?object $originalMessage): string
    {
        $greeting = (string) ($context['sender_greeting'] ?? 'Здравствуйте');
        $answer = (string) ($context['answer_text'] ?? '');
        $fullName = (string) ($context['sender_full_name'] ?? '');
        $phone = (string) ($context['sender_phone'] ?? '');
        $email = (string) ($context['sender_email'] ?? '');
        $token = (string) ($context['tracking_token'] ?? '');

        $text = "{$greeting},\n\n{$answer}\n\nС уважением,\n{$fullName}\n{$phone}\n{$email}\n\n---\nRef: {$token}\n";

        if ($originalMessage !== null) {
            $date = $this->formatRuDate($originalMessage->received_at ?? null);
            $from = (string) ($originalMessage->from_email ?? 'unknown');
            $quoted = (string) ($originalMessage->body_text ?? '');
            $quotedLines = implode("\n", array_map(static fn ($l) => '> ' . $l, explode("\n", $quoted)));
            $text .= "\n{$date}, <{$from}>:\n{$quotedLines}";
        }

        return $text;
    }

    private function formatRuDate(mixed $value): string
    {
        $months = [
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля', 5 => 'мая', 6 => 'июня',
            7 => 'июля', 8 => 'августа', 9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
        ];

        try {
            $ts = ($value !== null && $value !== '') ? strtotime((string) $value) : time();
            if ($ts === false) {
                $ts = time();
            }
        } catch (\Throwable) {
            $ts = time();
        }

        $day = (int) date('j', $ts);
        $month = $months[(int) date('n', $ts)] ?? '';
        $year = date('Y', $ts);
        $time = date('H:i', $ts);

        return "{$day} {$month} {$year} г., {$time}";
    }

    /**
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
