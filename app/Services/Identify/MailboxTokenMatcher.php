<?php

namespace App\Services\Identify;

use Illuminate\Support\Facades\DB;

/**
 * Поиск трекинг-токена неопознанного письма в его теме/теле — порт n8n-узлов
 * «Get Tokens for Mailbox» + «Match Token in Subject».
 *
 * Логика (как в n8n):
 *  1. Берём токены ВСЕХ писем, отправленных С ТОГО ЯЩИКА, на который пришёл ответ
 *     (email_queue.from_email = unidentified_emails.to_email), за окно lookback_days,
 *     по активным статусам (sent/opened/replied/in_conversation). Чистим эмодзи.
 *  2. Ищем полный token_clean (длиной ≥5) подстрокой в subject + body_text[:3000].
 *  3. Если не нашли — пробуем базовую часть токена (до первого дефиса, длиной ≥4).
 *
 * Это «мягкий» матч второго прохода: IncomingEmailRouter на приёме матчит только
 * точный токен, а здесь добираем письма с потерянным/искажённым хвостом токена.
 */
class MailboxTokenMatcher
{
    private const ACTIVE_STATUSES = ['sent', 'opened', 'replied', 'in_conversation'];
    private const EMOJI = ['🛠️', '⚙️', '🔧'];

    public function __construct(private readonly int $lookbackDays = 60)
    {
    }

    /**
     * @return array{queue_id:int,batch_id:?int,supplier_id:?int,tracking_token:string,token_clean:string,match_type:string}|null
     */
    public function match(string $mailbox, string $subject, ?string $bodyText): ?array
    {
        if ($mailbox === '') {
            return null;
        }

        $tokens = $this->tokensForMailbox($mailbox);
        if ($tokens === []) {
            return null;
        }

        $searchText = $subject . ' ' . mb_substr((string) $bodyText, 0, 3000);

        // Сначала — полный токен.
        foreach ($tokens as $t) {
            $clean = (string) $t->token_clean;
            if (mb_strlen($clean) < 5) {
                continue;
            }
            if (str_contains($searchText, $clean)) {
                return $this->result($t, $clean, 'full_token');
            }
        }

        // Затем — базовая часть токена (до первого дефиса).
        foreach ($tokens as $t) {
            $clean = (string) $t->token_clean;
            if (mb_strlen($clean) < 5) {
                continue;
            }
            $base = explode('-', $clean)[0];
            if (mb_strlen($base) < 4) {
                continue;
            }
            if (str_contains($searchText, $base)) {
                return $this->result($t, $clean, 'base_token');
            }
        }

        return null;
    }

    /**
     * @return array<int,object>
     */
    private function tokensForMailbox(string $mailbox): array
    {
        return DB::connection('reports')->table('email_queue')
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->where('sent_at', '>=', now()->subDays($this->lookbackDays))
            ->whereNotNull('tracking_token')
            ->where('tracking_token', '!=', '')
            ->where('from_email', $mailbox)
            ->distinct()
            ->get(['id as queue_id', 'batch_id', 'supplier_id', 'tracking_token'])
            ->map(function (object $row): object {
                $row->token_clean = str_replace(self::EMOJI, '', (string) $row->tracking_token);
                return $row;
            })
            ->all();
    }

    /**
     * @return array{queue_id:int,batch_id:?int,supplier_id:?int,tracking_token:string,token_clean:string,match_type:string}
     */
    private function result(object $token, string $clean, string $type): array
    {
        return [
            'queue_id' => (int) $token->queue_id,
            'batch_id' => $token->batch_id !== null ? (int) $token->batch_id : null,
            'supplier_id' => $token->supplier_id !== null ? (int) $token->supplier_id : null,
            'tracking_token' => (string) $token->tracking_token,
            'token_clean' => $clean,
            'match_type' => $type,
        ];
    }
}
