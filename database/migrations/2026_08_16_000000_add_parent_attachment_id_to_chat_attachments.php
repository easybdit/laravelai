<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets one attachment (a page image rendered from a PDF — see
 * PdfPageRenderer, config('ai.chat.attachments.pdf_vision_enabled')) point
 * back at the real attachment the user actually uploaded/selected, so both
 * ride along together when resolveAttachments() builds vision input —
 * without the frontend needing to know these extra rows exist or list
 * their IDs itself.
 *
 * Nullable, no foreign key — every attachment created before this migration
 * (and every one that isn't a PDF page image) simply has no parent, exactly
 * today's behavior.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('ai_chat_attachments', 'parent_attachment_id')) {
            return;
        }

        Schema::table('ai_chat_attachments', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_attachment_id')->nullable()->after('chat_message_id');
            $table->index('parent_attachment_id');
        });
    }

    public function down(): void
    {
        Schema::table('ai_chat_attachments', function (Blueprint $table) {
            $table->dropIndex(['parent_attachment_id']);
            $table->dropColumn('parent_attachment_id');
        });
    }
};
