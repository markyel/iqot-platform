<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_items', function (Blueprint $table) {
            $table->integer('position_number')->default(1)->after('request_id');
            $table->string('category', 100)->nullable()->after('brand');
            $table->foreignId('product_type_id')->nullable()->after('category');
            $table->foreignId('domain_id')->nullable()->after('product_type_id');
            $table->decimal('type_confidence', 5, 2)->nullable()->after('product_type_id');
            $table->decimal('domain_confidence', 5, 2)->nullable()->after('domain_id');
            $table->boolean('classification_needs_review')->default(false)->after('domain_confidence');
        });
    }

    public function down(): void
    {
        Schema::table('request_items', function (Blueprint $table) {
            $table->dropColumn([
                'position_number',
                'category',
                'product_type_id',
                'domain_id',
                'type_confidence',
                'domain_confidence',
                'classification_needs_review'
            ]);
        });
    }
};
