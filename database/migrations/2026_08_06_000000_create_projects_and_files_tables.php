<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 1 of 2 for Projects feature.
 * Creates: ai_projects, ai_project_files
 * Must run BEFORE 2026_08_06_000001 (which adds project_id to chat_sessions)
 *
 * Prefixed "ai_" (not the bare "projects"/"project_files" this shipped with
 * pre-release) so a package meant to drop into any host app doesn't collide
 * with an app's own — extremely common — "projects" table. Safe to change
 * here rather than add a rename migration: this table was never part of a
 * tagged release, so no installed site has ever created it under the old name.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_projects')) {
            Schema::create('ai_projects', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ai_project_files')) {
            Schema::create('ai_project_files', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('ai_projects')->cascadeOnDelete();
                $table->string('original_name');
                $table->string('stored_path');
                $table->string('mime_type')->default('text/plain');
                $table->enum('status', ['pending', 'ingested', 'failed'])->default('pending');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_project_files');
        Schema::dropIfExists('ai_projects');
    }
};
