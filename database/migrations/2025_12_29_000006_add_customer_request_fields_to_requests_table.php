<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->foreignId('client_organization_id')->nullable()->after('user_id');
            $table->string('request_number', 50)->nullable()->after('code');
            $table->boolean('is_customer_request')->default(0)->after('status');
            $table->integer('total_items')->default(0)->after('items_count');
            $table->text('notes')->nullable();

            $table->index('request_number');
            $table->index('is_customer_request');
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn([
                'client_organization_id',
                'request_number',
                'is_customer_request',
                'total_items',
                'notes'
            ]);
        });
    }
};
