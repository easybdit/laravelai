<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 2 of 2 for Projects feature.
 * Adds project_id FK to chat_sessions.
 * Depends on: chat_sessions (2026_08_05) + projects (2026_08_06_000000)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_sessions') && !Schema::hasColumn('chat_sessions', 'project_id')) {
            Schema::table('chat_sessions', function (Blueprint $table) {
                $table->foreignId('project_id')
                      ->nullable()
                      ->after('title')
                      ->constrained()
                      ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('chat_sessions') && Schema::hasColumn('chat_sessions', 'project_id')) {
            Schema::table('chat_sessions', function (Blueprint $table) {
                $table->dropForeign(['project_id']);
                $table->dropColumn('project_id');
            });
        }
    }
};
