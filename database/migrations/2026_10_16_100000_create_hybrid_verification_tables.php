<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ф3 «Гибрид» (docs/monitoring-design.md §2): проверочные КП к ценам сайтов и доверие к сайту.
 *  - mp_asks: что и у кого спрашиваем (позиция × сайт × поставщик, цена сайта на момент запроса);
 *    отправка — адресным письмом через техническую заявку программы в reports (решение 2026-10-15).
 *  - mp_verifications: сверки КП ↔ сайт (из проверочных писем и «бесплатно» — из КП обычных заявок).
 *  - mp_request_item_links: позиция обычной заявки (reports.request_items) ↔ канонический товар.
 *  - mp_sites.consecutive_mismatch: «два расхождения цены подряд — сразу уровень 0».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mp_asks')) {
            Schema::create('mp_asks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('site_id')->constrained('mp_sites')->cascadeOnDelete();
                $table->foreignId('listing_id')->constrained('mp_listings')->cascadeOnDelete();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('program_id')->nullable()->index();
                $table->unsignedInteger('supplier_id')->index();                 // reports.suppliers.id
                $table->enum('kind', ['verify', 'rfq'])->default('verify');
                $table->string('reason', 40)->nullable();                        // first|sample|price_changed|stale|due
                $table->decimal('site_price', 14, 2)->nullable();
                $table->char('site_currency', 3)->nullable();
                $table->boolean('site_vat_included')->nullable();
                $table->string('site_availability', 16)->nullable();
                $table->dateTime('site_observed_at')->nullable();
                $table->enum('status', ['planned', 'queued', 'sent', 'answered', 'expired', 'cancelled'])->default('planned');
                $table->unsignedInteger('request_item_id')->nullable()->index(); // reports.request_items.id (тех. заявка)
                $table->unsignedInteger('batch_id')->nullable();
                $table->unsignedInteger('email_queue_id')->nullable();
                $table->dateTime('queued_at')->nullable();
                $table->dateTime('sent_at')->nullable();
                $table->dateTime('answered_at')->nullable();
                $table->dateTime('expires_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'supplier_id']);
                $table->index(['site_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('mp_verifications')) {
            Schema::create('mp_verifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('site_id')->constrained('mp_sites')->cascadeOnDelete();
                $table->unsignedBigInteger('listing_id')->nullable();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedInteger('supplier_id');
                $table->unsignedBigInteger('ask_id')->nullable();
                $table->enum('source', ['ask', 'rfq']);                          // проверочное письмо | КП обычной заявки
                $table->unsignedInteger('request_item_id');
                $table->decimal('site_price', 14, 2)->nullable();
                $table->boolean('site_vat_included')->nullable();
                $table->decimal('kp_price', 14, 2)->nullable();
                $table->char('kp_currency', 3)->nullable();
                $table->boolean('kp_vat_included')->nullable();
                $table->decimal('diff_pct', 8, 3)->nullable();
                $table->enum('outcome', ['match', 'price_mismatch', 'match_error', 'basis_diff', 'availability', 'inconclusive']);
                $table->string('note', 255)->nullable();
                $table->unsignedTinyInteger('level_before')->nullable();
                $table->unsignedTinyInteger('level_after')->nullable();
                $table->dateTime('verified_at');
                $table->timestamps();
                $table->unique(['source', 'request_item_id', 'supplier_id']);    // идемпотентность сверки
                $table->index(['site_id', 'verified_at']);
            });
        }

        if (!Schema::hasTable('mp_request_item_links')) {
            Schema::create('mp_request_item_links', function (Blueprint $table) {
                $table->unsignedInteger('request_item_id')->primary();          // reports.request_items.id
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->enum('status', ['linked', 'ambiguous', 'none']);
                $table->dateTime('linked_at');
            });
        }

        if (Schema::hasTable('mp_sites') && !Schema::hasColumn('mp_sites', 'consecutive_mismatch')) {
            Schema::table('mp_sites', function (Blueprint $table) {
                $table->unsignedSmallInteger('consecutive_mismatch')->default(0)->after('consecutive_ok');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_request_item_links');
        Schema::dropIfExists('mp_verifications');
        Schema::dropIfExists('mp_asks');
        if (Schema::hasColumn('mp_sites', 'consecutive_mismatch')) {
            Schema::table('mp_sites', fn (Blueprint $table) => $table->dropColumn('consecutive_mismatch'));
        }
    }
};
