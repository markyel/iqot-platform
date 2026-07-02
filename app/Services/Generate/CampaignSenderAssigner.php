<?php

namespace App\Services\Generate;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Порт n8n-узлов «Get Sender IDs» + «Assign Senders to Batches» + «Get Sender».
 *
 * Назначает ящик-отправитель каждому батчу за один прогон (общий rotationIndex
 * для честного round-robin между батчами):
 *   - именная заявка (client_organization_id) → персональный sender этой орг.;
 *   - анонимная → round-robin по общему пулу (is_personal != 1).
 * Затем грузит полный профиль назначенного отправителя + его организацию.
 */
class CampaignSenderAssigner
{
    private const CONN = 'reports';

    /** Курсор ротации общих отправителей (как rotationIndex в n8n). */
    private int $rotationIndex = 0;

    /** @var array<int,array<string,mixed>> кэш полного профиля sender по id */
    private array $senderCache = [];

    /**
     * Назначает отправителей всем батчам по порядку (мутирует $batches).
     *
     * @param array<int,Batch> $batches
     */
    public function assign(array $batches): void
    {
        $pool = $this->loadSenderPool();
        if (empty($pool)) {
            Log::warning('CampaignSenderAssigner: пустой пул отправителей (нет активных senders).');
            return;
        }

        $personal = array_values(array_filter($pool, static fn ($s) => (int) ($s['is_personal'] ?? 0) === 1));
        $shared = array_values(array_filter($pool, static fn ($s) => (int) ($s['is_personal'] ?? 0) !== 1));

        foreach ($batches as $batch) {
            $assignedId = $this->pickSenderId($batch, $pool, $personal, $shared);
            if ($assignedId === null) {
                continue;
            }
            $batch->sender = $this->loadFullSender($assignedId);
        }
    }

    /**
     * @param array<int,array<string,mixed>> $pool
     * @param array<int,array<string,mixed>> $personal
     * @param array<int,array<string,mixed>> $shared
     */
    private function pickSenderId(Batch $batch, array $pool, array $personal, array $shared): ?int
    {
        if ($batch->isCustomerRequest && $batch->clientOrganizationId) {
            $orgId = (int) $batch->clientOrganizationId;
            foreach ($personal as $s) {
                if ((int) ($s['client_organization_id'] ?? 0) === $orgId) {
                    return (int) $s['id'];
                }
            }
            if (count($shared) > 0) {
                $id = (int) $shared[$this->rotationIndex % count($shared)]['id'];
                $this->rotationIndex++;
                return $id;
            }
            return (int) $pool[0]['id']; // fallback_any
        }

        // Анонимная заявка — только общие senders.
        if (count($shared) > 0) {
            $id = (int) $shared[$this->rotationIndex % count($shared)]['id'];
            $this->rotationIndex++;
            return $id;
        }
        $id = (int) $pool[$this->rotationIndex % count($pool)]['id'];
        $this->rotationIndex++;
        return $id;
    }

    /**
     * Порт «Get Sender IDs»: пул активных отправителей с метрикой недавнего использования.
     *
     * @return array<int,array<string,mixed>>
     */
    private function loadSenderPool(): array
    {
        $rows = DB::connection(self::CONN)->select(
            <<<'SQL'
SELECT
  s.id,
  s.sender_name,
  s.client_organization_id,
  s.is_personal,
  s.emails_sent_today,
  COALESCE(recent.last_batch_id, 0) as last_batch_id,
  COALESCE(recent.usage_count, 0) as recent_usage_count
FROM senders s
LEFT JOIN (
  SELECT
    sender_id,
    MAX(id) as last_batch_id,
    COUNT(*) as usage_count
  FROM email_batches
  WHERE id >= (SELECT COALESCE(MAX(id), 0) - 20 FROM email_batches)
  GROUP BY sender_id
) recent ON s.id = recent.sender_id
WHERE s.is_active = 1 AND s.sending_disabled = 0
ORDER BY
  COALESCE(recent.last_batch_id, 0) ASC,
  COALESCE(recent.usage_count, 0) ASC,
  RAND()
LIMIT 100
SQL
        );

        return array_map(static fn ($r) => (array) $r, $rows);
    }

    /**
     * Порт «Get Sender»: полный профиль отправителя + его организация.
     *
     * @return array<string,mixed>
     */
    private function loadFullSender(int $senderId): array
    {
        if (isset($this->senderCache[$senderId])) {
            return $this->senderCache[$senderId];
        }

        $row = DB::connection(self::CONN)->selectOne(
            <<<'SQL'
SELECT
  s.id,
  s.sender_name,
  s.sender_full_name,
  s.phone,
  s.email,
  s.email_style,
  s.email_greeting,
  s.token_template_id,
  s.preferred_template_id,
  s.daily_limit,
  s.emails_sent_today,
  s.client_organization_id,
  co.name as organization_name,
  co.inn as organization_inn,
  co.kpp as organization_kpp,
  co.legal_address as organization_legal_address,
  co.actual_address as organization_actual_address,
  co.phone as organization_phone,
  co.email as organization_email,
  co.director_name as organization_director_name
FROM senders s
LEFT JOIN client_organizations co ON s.client_organization_id = co.id
WHERE s.id = ?
SQL,
            [$senderId]
        );

        $sender = $row ? (array) $row : ['id' => $senderId];
        $this->senderCache[$senderId] = $sender;
        return $sender;
    }
}
