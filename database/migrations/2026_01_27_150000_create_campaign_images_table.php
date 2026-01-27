<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('campaign_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->onDelete('cascade');
            $table->string('original_src'); // Оригинальный src из шаблона
            $table->string('file_path'); // Путь к загруженному файлу
            $table->string('cid'); // Content-ID для email
            $table->timestamps();

            $table->index(['campaign_id', 'original_src']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_images');
    }
};
