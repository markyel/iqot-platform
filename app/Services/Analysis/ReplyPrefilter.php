<?php

namespace App\Services\Analysis;

use Illuminate\Support\Facades\DB;

/**
 * Письма, которым ИИ-разбор не нужен (21.10.2026: разбор — главная статья расхода ИИ, а заметная часть ответов —
 * пустые письма, автоответы «мы получили ваше письмо» и повторные отправки одного и того же). Возвращает готовую
 * классификацию в формате SupplierReplyAnalyzer или null — тогда письмо идёт в ИИ как обычно.
 *
 * Осторожно: всё, где может быть цена, вопрос или просьба исключить из рассылки, — в ИИ.
 */
class ReplyPrefilter
{
    private const AUTO_RE = '/(получил[иа]?|поступил[оа]?|принят[оа]?|зарегистрирован[оа]?)\s+(ваше|ваш|вашу)\s+(письмо|сообщение|обращение|запрос|заявк)'
        . '|мы\s+(с\s+вами\s+)?свяжемся|в\s+(ближайшее|кратчайшее)\s+(рабочее\s+)?время\s+(мы\s+)?(вам\s+)?(ответим|свяжемся|с\s+вами\s+свяжутся)'
        . '|свяжутся\s+наши\s+специалист|автоматическ\w*\s+(ответ|уведомлен)|auto-?reply|out\s+of\s+(the\s+)?office|нахожусь\s+в\s+отпуске'
        . '|спасибо\s+за\s+(ваше\s+)?обращение/iu';

    private const KEEP_RE = '/\?|руб|₽|\bр\.|цен|стоим|прайс|счет|счёт|кп\b|коммерческ|наличи|срок|отпис|исключ|удалит|не\s+присылайте|рассылк/iu';

    /** @return array<string,mixed>|null */
    public function classify(string $body, array $attachments, object $message): ?array
    {
        $text = trim($body);

        // 1. Пусто (только цитата / без текста) и без вложений.
        if ($attachments === [] && str_starts_with($text, '[ПИСЬМО БЕЗ')) {
            return $this->result('empty_reply', 'Письмо без нового текста и без вложений (без ИИ)');
        }

        // 2. Повтор: тот же отправитель уже прислал в этой беседе такой же текст, и он разобран.
        $norm = $this->norm($text);
        if ($norm !== '' && mb_strlen($norm) >= 20 && $attachments === []) {
            $prev = DB::connection('reports')->table('email_messages')
                ->where('conversation_id', $message->conversation_id)->where('direction', 'incoming')
                ->where('ai_processed', 1)->where('id', '<', $message->message_id)
                ->whereRaw('LOWER(from_email) = ?', [mb_strtolower((string) ($message->from_email ?? ''))])
                ->where('created_at', '>=', now()->subDays(3))
                ->orderByDesc('id')->limit(5)->get(['body_text', 'body_html', 'ai_classification']);
            $cleaner = new EmailBodyCleaner();
            foreach ($prev as $p) {
                if ($this->norm($cleaner->clean($p->body_text, $p->body_html)) === $norm) {
                    $type = (string) (json_decode((string) $p->ai_classification, true)['email_type'] ?? 'other');

                    // Офферы/вопросы первого письма уже записаны — повтор их не дублирует.
                    return $this->result($type === 'offer' || $type === 'mixed' ? 'other' : $type, 'Повтор уже разобранного письма (без ИИ)');
                }
            }
        }

        // 3. Явный автоответ: коротко, без вложений, без признаков цены/вопроса/отписки.
        if ($attachments === [] && mb_strlen($text) <= 600 && preg_match(self::AUTO_RE, $text) && !preg_match(self::KEEP_RE, $text)) {
            return $this->result('auto_reply', 'Автоответ о получении письма (без ИИ)');
        }

        return null;
    }

    private function norm(string $t): string
    {
        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($t)));
    }

    /** @return array<string,mixed> */
    private function result(string $type, string $summary): array
    {
        return [
            'email_type' => $type, 'rejection_reason' => null, 'unsubscribe' => false, 'unsubscribe_reason' => null,
            'has_offers' => false, 'offers' => [], 'has_questions' => false, 'questions' => [],
            'summary' => $summary, 'fetch_urls' => [], 'prefiltered' => true,
        ];
    }
}
