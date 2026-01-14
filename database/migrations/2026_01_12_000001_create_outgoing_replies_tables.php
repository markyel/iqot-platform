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
        Schema::create('outgoing_replies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('supplier_question_id')->nullable();
            $table->unsignedBigInteger('sender_id');
            $table->unsignedBigInteger('supplier_id');
            $table->string('from_email');
            $table->string('to_email');
            $table->string('subject', 500)->nullable();
            $table->text('body_text')->nullable();
            $table->text('body_html')->nullable();
            $table->string('in_reply_to', 500)->nullable();
            $table->text('references_header')->nullable();
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('conversation_id');
            $table->index('supplier_question_id');
            $table->index('sender_id');
            $table->index('supplier_id');
            $table->index('status');
        });

        Schema::create('outgoing_reply_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('outgoing_reply_id');
            $table->string('file_id')->nullable();
            $table->string('file_name');
            $table->string('mime_type', 100)->nullable();
            $table->integer('file_size')->nullable();
            $table->string('file_type', 50)->nullable();
            $table->longText('file_data')->nullable(); // base64 encoded
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('outgoing_reply_id')
                ->references('id')
                ->on('outgoing_replies')
                ->onDelete('cascade');

            $table->index('outgoing_reply_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outgoing_reply_attachments');
        Schema::dropIfExists('outgoing_replies');
    }
};
