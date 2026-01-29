<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->boolean('email_validated')->default(false)->after('error_message');
            $table->string('validation_status')->nullable()->after('email_validated'); // valid, invalid, skipped
            $table->string('validation_reason')->nullable()->after('validation_status');
            $table->string('validation_provider')->nullable()->after('validation_reason'); // basic, neverbounce, emaillistverify, datavalidation
            $table->timestamp('validated_at')->nullable()->after('validation_provider');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->dropColumn([
                'email_validated',
                'validation_status',
                'validation_reason',
                'validation_provider',
                'validated_at'
            ]);
        });
    }
};
