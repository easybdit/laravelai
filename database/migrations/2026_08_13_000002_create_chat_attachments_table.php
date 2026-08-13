<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('chat_attachments')) {
            Schema::create('chat_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('chat_session_id')->constrained()->cascadeOnDelete();
                $table->foreignId('chat_message_id')->nullable()->constrained()->nullOnDelete();
                $table->enum('type', ['image', 'document']);
                $table->string('original_name');
                $table->string('stored_path');
                $table->string('mime_type', 100);
                $table->unsignedBigInteger('size')->default(0);
                $table->longText('extracted_text')->nullable();
                $table->enum('status', ['pending', 'ready', 'error'])->default('pending');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_attachments');
    }
};
