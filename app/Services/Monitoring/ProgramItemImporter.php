<?php

namespace App\Services\Monitoring;

use App\Jobs\Monitoring\ClassifyMonitoringCategoriesJob;
use App\Jobs\Monitoring\ImportProgramItemsJob;
use App\Models\Monitoring\MonitoringProgram;
use App\Models\Monitoring\MpImport;
use App\Models\Monitoring\ProgramItem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Приём номенклатуры в программу мониторинга (docs/monitoring-f0-spec.md §5).
 *
 * queue*() кладёт пачку в файл и ставит ImportProgramItemsJob; process() — upsert
 * позиций по client_ref чанками, сопоставление изменённых с каноническими товарами,
 * потолок плана построчно (plan_limit_exceeded), счётчики программы.
 */
class ProgramItemImporter
{
    private const DISK = 'local';

    public function __construct(
        private readonly ProductIdentityResolver $resolver,
        private readonly MonitoringPlanLimits $limits,
    ) {
    }

    /** @param array<int,array<string,mixed>> $rows */
    public function queueRows(MonitoringProgram $program, array $rows, string $source = 'api', ?string $idempotencyKey = null, bool $dispatch = true): MpImport
    {
        $import = MpImport::create([
            'program_id' => $program->id,
            'source' => $source,
            'idempotency_key' => $idempotencyKey,
            'status' => 'queued',
            'rows_total' => count($rows),
        ]);
        $path = "monitoring/imports/{$import->external_id}.json";
        Storage::disk(self::DISK)->put($path, json_encode($rows, JSON_UNESCAPED_UNICODE));
        $import->forceFill(['payload_path' => $path])->save();

        if ($dispatch) {
            ImportProgramItemsJob::dispatch($import->id);
        }

        return $import;
    }

    /** Синхронная обработка импорта (из джоба или команды). */
    public function process(MpImport $import): void
    {
        $import->forceFill(['status' => 'processing'])->save();
        $program = MonitoringProgram::findOrFail($import->program_id);

        try {
            $rows = json_decode((string) Storage::disk(self::DISK)->get((string) $import->payload_path), true);
            if (!is_array($rows)) {
                throw new \RuntimeException('import payload is not a JSON array');
            }

            $limits = $this->limits->forUser($program->user);
            $ok = 0;
            $failed = 0;
            $errors = [];
            $toResolve = [];

            // Дубли client_ref внутри пачки — последняя строка побеждает.
            $byRef = [];
            foreach ($rows as $i => $row) {
                $ref = trim((string) ($row['client_ref'] ?? ''));
                $byRef[$ref !== '' ? $ref : "__row_{$i}"] = [$i, $row];
            }

            $activeCount = ProgramItem::query()->where('program_id', $program->id)->where('active', true)->count();

            // Категории заказчика (client_category, как в заявках) — один upsert на категорию пачки.
            $categoryIds = [];
            if ($program->api_client_id) {
                $registry = app(\App\Services\Api\ClientCategoryRegistry::class);
                foreach ($byRef as [$i, $row]) {
                    $code = is_array($row['client_category'] ?? null) ? (string) ($row['client_category']['code'] ?? '') : '';
                    if ($code !== '' && !isset($categoryIds[$code])) {
                        $categoryIds[$code] = $registry->upsert((int) $program->api_client_id, $row['client_category']);
                    }
                }
            }

            foreach (array_chunk($byRef, (int) config('monitoring.import.chunk', 500), true) as $chunk) {
                foreach ($chunk as [$i, $row]) {
                    $error = $this->validate($row);
                    if ($error !== null) {
                        $failed++;
                        $this->pushError($errors, $i, $row, $error);
                        continue;
                    }

                    $ref = trim((string) $row['client_ref']);
                    $active = !array_key_exists('active', $row) || (bool) $row['active'];
                    $existing = ProgramItem::query()->where('program_id', $program->id)->where('client_ref', $ref)->first();
                    $wasActive = $existing !== null && $existing->active;

                    $becomesActive = $active && !$wasActive;
                    if ($becomesActive && $activeCount >= $limits['max']) {
                        $failed++;
                        $this->pushError($errors, $i, $row, 'plan_limit_exceeded');
                        continue;
                    }

                    $attrs = [
                        'raw_name' => mb_substr(trim((string) $row['name']), 0, 1000),
                        'raw_brand' => $this->str($row['brand'] ?? null, 255),
                        'raw_article' => $this->str($row['article'] ?? null, 255),
                        'raw_unit' => $this->str($row['unit'] ?? null, 32),
                        'raw_attributes' => is_array($row['attributes'] ?? null) ? $row['attributes'] : null,
                        'active' => $active,
                    ];
                    // Категорию меняем, только если её прислали (повторная загрузка без неё не стирает).
                    $catCode = is_array($row['client_category'] ?? null) ? (string) ($row['client_category']['code'] ?? '') : '';
                    if ($catCode !== '' && !empty($categoryIds[$catCode])) {
                        $attrs['client_category_id'] = $categoryIds[$catCode];
                    }

                    if (!$existing) {
                        $item = ProgramItem::create($attrs + ['program_id' => $program->id, 'client_ref' => $ref]);
                        $toResolve[] = $item->id;
                    } else {
                        $identityChanged = $existing->raw_article !== $attrs['raw_article']
                            || $existing->raw_brand !== $attrs['raw_brand']
                            || ($existing->raw_article === null && $existing->raw_name !== $attrs['raw_name']);
                        $existing->fill($attrs);
                        if ($identityChanged) {
                            $existing->forceFill(['product_id' => null, 'match_status' => ProgramItem::MATCH_PENDING]);
                            $toResolve[] = $existing->id;
                        }
                        $existing->save();
                    }

                    if ($becomesActive) {
                        $activeCount++;
                    } elseif (!$active && $wasActive) {
                        $activeCount--;
                    }
                    $ok++;
                }

                $import->forceFill(['rows_ok' => $ok, 'rows_failed' => $failed])->save();
            }

            $newProducts = [];
            foreach (array_chunk($toResolve, 200) as $ids) {
                foreach (ProgramItem::query()->whereIn('id', $ids)->get() as $item) {
                    try {
                        $this->resolver->resolve($item);
                        if ($item->product_id) {
                            $newProducts[] = $item->product_id;
                        }
                    } catch (\Throwable $e) {
                        Log::warning('ProgramItemImporter: resolve failed', ['item_id' => $item->id, 'error' => $e->getMessage()]);
                    }
                }
            }

            $program->refreshCounters();
            $import->forceFill([
                'status' => 'done',
                'rows_ok' => $ok,
                'rows_failed' => $failed,
                'errors' => $errors ?: null,
            ])->save();

            // Классификация по таксономии IQOT — ПО КАТЕГОРИИ заказчика (≤1 GPT на категорию), а не
            // поштучно gpt-4o (решение владельца 2026-10-15). Позиции без категории не классифицируем:
            // классификация нужна только будущему RFQ-режиму.
            if ($categoryIds !== []) {
                ClassifyMonitoringCategoriesJob::dispatch((int) $program->id);
            }
        } catch (\Throwable $e) {
            Log::error('ProgramItemImporter: import failed', ['import_id' => $import->id, 'error' => $e->getMessage()]);
            $import->forceFill(['status' => 'failed', 'errors' => [['row' => null, 'error' => mb_substr($e->getMessage(), 0, 300)]]])->save();
            throw $e;
        }
    }

    /** @param array<string,mixed> $row */
    private function validate(array $row): ?string
    {
        $ref = trim((string) ($row['client_ref'] ?? ''));
        if ($ref === '' || mb_strlen($ref) > 191) {
            return 'client_ref_invalid';
        }
        $name = trim((string) ($row['name'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 1000) {
            return 'name_invalid';
        }

        return null;
    }

    /** @param array<int,array<string,mixed>> $errors @param array<string,mixed> $row */
    private function pushError(array &$errors, int $index, array $row, string $code): void
    {
        if (count($errors) < 100) {
            $errors[] = ['row' => $index, 'client_ref' => $row['client_ref'] ?? null, 'error' => $code];
        }
    }

    private function str(mixed $v, int $max): ?string
    {
        $s = trim((string) ($v ?? ''));

        return $s === '' ? null : mb_substr($s, 0, $max);
    }
}
