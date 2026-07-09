<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Фаза 2 (планировщик): backlog «интентов» + цель заявки.
 *
 * send_intents — дешёвый спрос: кому (supplier) по какой заявке (request) хотим
 * написать. HTML НЕ здесь (рендерим лениво под ёмкость). Единица покрытия — позиция
 * (request_item), но интент на уровне (request×supplier): payload = активные матчащие
 * позиции на момент рендера. UNIQUE(request_id, supplier_id) — дедуп.
 *
 * requests.offer_target — цель по офферам на позицию (null → конфиг-дефолт 4).
 * requests.max_reach — режим макс. охвата (шлём всем кандидатам, позиции не «закрываем»).
 */
return new class extends Migration
{
    private string $conn = 'reports';

    public function up(): void
    {
        if (!Schema::connection($this->conn)->hasTable('send_intents')) {
            Schema::connection($this->conn)->create('send_intents', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedInteger('request_id')->index();
                $t->unsignedInteger('supplier_id')->index();
                // backlog=ждёт рендера; rendered=отрендерен в email_queue; dropped=заявка
                // закрыта/снят. Индекс на выборку планировщиком.
                $t->string('status', 20)->default('backlog')->index();
                $t->unsignedTinyInteger('tier')->nullable();      // 1 hot / 2 warm / 3 cold
                $t->decimal('score', 9, 3)->nullable();           // приоритет (кэш планировщика)
                $t->unsignedInteger('batch_id')->nullable();      // проставляется при рендере
                $t->unsignedBigInteger('email_queue_id')->nullable();
                $t->unsignedSmallInteger('attempts')->default(0);
                $t->string('last_reason', 120)->nullable();
                $t->timestamps();
                $t->unique(['request_id', 'supplier_id']);
            });
        }

        Schema::connection($this->conn)->table('requests', function (Blueprint $t) {
            if (!Schema::connection($this->conn)->hasColumn('requests', 'offer_target')) {
                $t->unsignedSmallInteger('offer_target')->nullable()->after('status');
            }
            if (!Schema::connection($this->conn)->hasColumn('requests', 'max_reach')) {
                $t->boolean('max_reach')->default(false)->after('offer_target');
            }
        });
    }

    public function down(): void
    {
        Schema::connection($this->conn)->dropIfExists('send_intents');
        Schema::connection($this->conn)->table('requests', function (Blueprint $t) {
            foreach (['offer_target', 'max_reach'] as $c) {
                if (Schema::connection($this->conn)->hasColumn('requests', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
