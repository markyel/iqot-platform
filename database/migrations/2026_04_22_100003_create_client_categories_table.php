<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_client_id')->constrained('api_clients')->onDelete('cascade');
            $table->string('external_code', 128);
            $table->json('path_segments');
            $table->string('full_path', 512);
            $table->string('leaf_name', 255);
            $table->unsignedTinyInteger('depth');
            $table->json('raw_metadata')->nullable();
            $table->timestamp('first_seen_at')->useCurrent();
            $table->timestamp('last_seen_at')->useCurrent();
            $table->unsignedBigInteger('hit_count')->default(0);

            $table->unique(['api_client_id', 'external_code'], 'uniq_client_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_categories');
    }
};
