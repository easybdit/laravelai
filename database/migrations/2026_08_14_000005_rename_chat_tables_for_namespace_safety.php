<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Same real problem 2026_08_14_000002 already fixed for "projects" /
 * "project_files" — "chat_sessions" / "chat_messages" (shipped since
 * v1.0.0) and "chat_attachments" (v2.0.0) are bare, generic names with a
 * real chance of colliding with a host app's own tables (a CMS, a support-
 * ticket system, or any other chat/messaging feature genuinely might have
 * a "chat_messages" of its own). Every other table this package creates
 * already carries an `ai_` prefix (ai_admins, ai_documents, ai_projects,
 * ai_project_files, ai_settings) — these three were simply missed.
 *
 * Renamed, not recreated, for the same reason as 2026_08_14_000002: a
 * fresh `Schema::create('ai_chat_sessions', ...)` would silently orphan
 * every existing install's real conversation history, which this
 * migration guards against on both ends (fresh install renames the table
 * this same `migrate` run just created moments ago; an existing install
 * renames its real data in place, preserving every row).
 *
 * Rename order (sessions, then messages, then attachments) matches
 * dependency order — chat_messages.chat_session_id and
 * chat_attachments.chat_session_id/chat_message_id are real foreign keys
 * (->constrained()->cascadeOnDelete()) pointing at the tables renamed
 * before them. Confirmed safe across all three supported drivers by the
 * exact same pattern already in production since 2026_08_14_000002 (that
 * migration's own project_files.project_id FK survived the projects
 * rename identically) — MySQL/Postgres track foreign keys independently
 * of the referenced table's name, and SQLite's ALTER TABLE RENAME TO has
 * rewritten other tables' REFERENCES clauses automatically since 3.25.0
 * (2018), long predating any PHP version this package supports.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_sessions') && !Schema::hasTable('ai_chat_sessions')) {
            Schema::rename('chat_sessions', 'ai_chat_sessions');
        }

        if (Schema::hasTable('chat_messages') && !Schema::hasTable('ai_chat_messages')) {
            Schema::rename('chat_messages', 'ai_chat_messages');
        }

        if (Schema::hasTable('chat_attachments') && !Schema::hasTable('ai_chat_attachments')) {
            Schema::rename('chat_attachments', 'ai_chat_attachments');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_chat_attachments') && !Schema::hasTable('chat_attachments')) {
            Schema::rename('ai_chat_attachments', 'chat_attachments');
        }

        if (Schema::hasTable('ai_chat_messages') && !Schema::hasTable('chat_messages')) {
            Schema::rename('ai_chat_messages', 'chat_messages');
        }

        if (Schema::hasTable('ai_chat_sessions') && !Schema::hasTable('chat_sessions')) {
            Schema::rename('ai_chat_sessions', 'chat_sessions');
        }
    }
};
