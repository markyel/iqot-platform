<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_inbox', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_submission_id')->constrained('api_submissions')->onDelete('cascade');
            $table->json('raw_payload');
            $table->enum('status', ['pending', 'processing', 'classified', 'failed'])->default('pending');
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->timestamps();

            $table->unique('api_submission_id', 'uniq_submission');
            $table->index(['status', 'updated_at'], 'idx_status_updated');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_inbox');
    }
};
