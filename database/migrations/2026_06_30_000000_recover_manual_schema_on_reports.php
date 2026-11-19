<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Довосстановление схемы reports после потери базы Beget (02.11.2026).
 *
 * 30.06–06.07.2026 эти таблицы и колонки создавались на проде вручную, без миграций: их нет
 * ни в дампе 28.01.2026, ни в git. Определения восстановлены по использованию в коде (типы —
 * по смыслу, точных DDL не сохранилось). Дата миграции — до правок 02.07–06.07, которые
 * дописывают deferred_batches (product_type_id/domain_id after sender_id, only_supplier_ids,
 * wave): здесь таблица создаётся в состоянии на 01.07, без этих колонок.
 *
 * Идемпотентна: существующие таблицы и колонки пропускает (на базе, где они уже есть, ничего не меняет).
 */
return new class extends Migration
{
    private string $conn = 'reports';

    public function up(): void
    {
        $schema = Schema::connection($this->conn);

        // Отложенные батчи генерации: гейт качества (pending→ready), накопители по загрузке
        // получателей и отсрочки по капасити/бану (accumulating→processing→done).
        if (!$schema->hasTable('deferred_batches')) {
            $schema->create('deferred_batches', function (Blueprint $table) {
                $table->increments('id');
                $table->json('request_ids')->nullable();
                $table->json('item_ids')->nullable();
                $table->unsignedInteger('sender_id')->nullable();
                $table->json('found_domains')->nullable();
                $table->unsignedInteger('candidates_total')->default(0);
                $table->unsignedInteger('candidates_done')->default(0);
                $table->string('status', 32)->default('pending');
                $table->string('reason', 255)->nullable();
                $table->timestamps();
                $table->index(['status', 'id']);
                $table->index(['reason', 'status']);
            });
        }

        // Стоп-лист доменов получателей (генерация, мониторинг и отправка не пишут на эти домены).
        if (!$schema->hasTable('blocked_domains')) {
            $schema->create('blocked_domains', function (Blueprint $table) {
                $table->increments('id');
                $table->string('domain', 255)->unique();
                $table->string('reason', 255)->nullable();
                $table->timestamps();
            });
            $now = now();
            DB::connection($this->conn)->table('blocked_domains')->insertOrIgnore([
                ['domain' => 'myzip.ru', 'reason' => 'домен клиента', 'created_at' => $now, 'updated_at' => $now],
                ['domain' => 'mylift.ru', 'reason' => 'домен клиента', 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        // Поставщики, найденные поиском по ходу батча, — досыл им на волне 2 (emails:dispatch-followup).
        if (!$schema->hasTable('campaign_discoveries')) {
            $schema->create('campaign_discoveries', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('batch_id');
                $table->unsignedInteger('request_id')->nullable();
                $table->unsignedInteger('supplier_id');
                $table->string('source_url', 500)->nullable();
                $table->boolean('emailed')->default(false);
                $table->timestamp('created_at')->nullable();
                $table->unique(['batch_id', 'supplier_id']);
                $table->index(['emailed', 'batch_id']);
            });
        }

        // Отписка поставщика (SupplierUnsubscribeEscalator) и прежние пауза/личный интервал адреса,
        // которые ещё читают диспетчер и подбор поставщиков.
        $this->addColumns('suppliers', [
            'unsubscribe_count' => fn (Blueprint $t) => $t->unsignedInteger('unsubscribe_count')->default(0),
            'last_unsubscribe_at' => fn (Blueprint $t) => $t->timestamp('last_unsubscribe_at')->nullable(),
            'unsubscribe_reason' => fn (Blueprint $t) => $t->string('unsubscribe_reason', 255)->nullable(),
            'unsubscribe_until' => fn (Blueprint $t) => $t->timestamp('unsubscribe_until')->nullable(),
            'send_interval_override_seconds' => fn (Blueprint $t) => $t->unsignedInteger('send_interval_override_seconds')->nullable(),
        ]);

        // Волна письма: 1 — сразу, 2 — досыл, 3 — придержанный холодный резерв.
        $this->addColumns('email_queue', [
            'wave' => fn (Blueprint $t) => $t->unsignedTinyInteger('wave')->nullable()->default(1),
        ]);
        if ($schema->hasColumn('email_queue', 'wave') && !$schema->hasIndex('email_queue', 'idx_email_queue_wave_status')) {
            $schema->table('email_queue', fn (Blueprint $t) => $t->index(['wave', 'status', 'scheduled_at'], 'idx_email_queue_wave_status'));
        }

        // Снимок контекста генерации батча (тело, ящик, шаблон, заявки) — пересборка писем волны 2.
        $this->addColumns('email_batches', [
            'gen_context' => fn (Blueprint $t) => $t->longText('gen_context')->nullable(),
        ]);
    }

    public function down(): void
    {
        // Откат намеренно пустой: это восстановление боевой схемы, а не новая функциональность.
    }

    /** @param array<string, callable(Blueprint): mixed> $columns */
    private function addColumns(string $tableName, array $columns): void
    {
        $schema = Schema::connection($this->conn);
        $missing = array_filter($columns, fn ($def, $col) => !$schema->hasColumn($tableName, $col), ARRAY_FILTER_USE_BOTH);
        if ($missing === []) {
            return;
        }
        $schema->table($tableName, function (Blueprint $table) use ($missing) {
            foreach ($missing as $def) {
                $def($table);
            }
        });
    }
};
