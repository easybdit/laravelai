<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 1 of 2 for Projects feature.
 * Creates: projects, project_files
 * Must run BEFORE 2026_08_06_000001 (which adds project_id to chat_sessions)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('projects')) {
            Schema::create('projects', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('project_files')) {
            Schema::create('project_files', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
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
        Schema::dropIfExists('project_files');
        Schema::dropIfExists('projects');
    }
};
