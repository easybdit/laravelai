<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the 'queued' status (used when config('ai.rag.queue_ingestion') is
 * enabled — the file has been handed to IngestDocumentJob but the job
 * hasn't run yet) to the project files status enum.
 *
 * This migration always runs after 2026_08_14_000002 (which renames
 * project_files -> ai_project_files), so the table is always named
 * ai_project_files by the time this executes — for both a fresh install
 * (renamed moments earlier in the same `migrate` run) and an existing
 * install being upgraded. The hasTable() fallback below only matters for
 * the unlikely case described in that rename migration's own comment
 * (an already-upgraded install where the rename never ran).
 *
 * Every driver Laravel's simple ->enum() column type supports here
 * actually enforces the constraint, just not as a native SQL ENUM type in
 * every case:
 *  - MySQL: a real ENUM column — ALTER ... MODIFY is the only way to widen it.
 *  - SQLite: `varchar check (status in (...))` — SQLite *does* enforce
 *    CHECK constraints, confirmed the hard way (writing 'queued' without
 *    this migration fails with "CHECK constraint failed: status"). SQLite
 *    has no ALTER COLUMN, so the constraint can only change via Laravel's
 *    ->change(), which rebuilds the table — natively since Laravel 11, no
 *    doctrine/dbal required (this package doesn't depend on dbal).
 *  - Postgres: `varchar(255) check (status in (...))` — same story as
 *    SQLite, handled the same way via ->change().
 * Both non-MySQL branches are wrapped in try/catch: on an older
 * Laravel/DBAL combination that can't rebuild the table here, this
 * degrades to a no-op rather than breaking the migration — 'queued' would
 * then only be rejected by that specific old install's DB layer, not by
 * anything in this package (ProjectFile::status is a plain string
 * attribute, not a PHP-level enum).
 */
return new class extends Migration
{
    private function resolveTable(): ?string
    {
        $table = Schema::hasTable('ai_project_files') ? 'ai_project_files' : 'project_files';
        return Schema::hasTable($table) ? $table : null;
    }

    public function up(): void
    {
        $table = $this->resolveTable();
        if (!$table) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE {$table} MODIFY status ENUM('pending','ingested','queued','failed') NOT NULL DEFAULT 'pending'");
            return;
        }

        if ($driver === 'sqlite' || $driver === 'pgsql') {
            try {
                Schema::table($table, function (Blueprint $t) {
                    $t->enum('status', ['pending', 'ingested', 'queued', 'failed'])->default('pending')->change();
                });
            } catch (\Throwable $e) {
                // See class docblock — soft degrade, not a hard migration failure.
            }
        }
    }

    public function down(): void
    {
        $table = $this->resolveTable();
        if (!$table) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE {$table} MODIFY status ENUM('pending','ingested','failed') NOT NULL DEFAULT 'pending'");
            return;
        }

        if ($driver === 'sqlite' || $driver === 'pgsql') {
            try {
                Schema::table($table, function (Blueprint $t) {
                    $t->enum('status', ['pending', 'ingested', 'failed'])->default('pending')->change();
                });
            } catch (\Throwable $e) {
                // See class docblock — soft degrade, not a hard migration failure.
            }
        }
    }
};
