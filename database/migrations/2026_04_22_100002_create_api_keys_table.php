<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_client_id')->constrained('api_clients')->onDelete('cascade');
            $table->string('key_hash', 128);
            $table->string('key_prefix', 24);
            $table->string('key_last4', 4);
            $table->string('name', 100);
            $table->json('ip_whitelist')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->unsignedBigInteger('request_count')->default(0);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique('key_hash', 'uniq_key_hash');
            $table->index(['api_client_id', 'revoked_at'], 'idx_client_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
