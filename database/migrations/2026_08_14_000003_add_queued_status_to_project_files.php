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
 *    doctrine/dbal required (this package doesn't depend on dbal). Real
 *    local SQLite runs confirm this path works.
 *  - Postgres: also `varchar(255) check ("status" in (...))` (see
 *    PostgresGrammar::typeEnum()) — but unlike SQLite, ->change() here
 *    was found live (real GitHub Actions Postgres service container, not
 *    a guess) to silently fail to actually widen the constraint: a
 *    dedicated regression test asserting a 'queued' status row can be
 *    created got a 500 (constraint violation) instead of 201, meaning
 *    the try/catch below WAS silently swallowing a real failure on this
 *    driver specifically, exactly the risk the class docblock warned
 *    about, now confirmed rather than theoretical. Fixed with direct
 *    DROP/ADD CONSTRAINT raw SQL instead of relying on ->change() for
 *    Postgres: an inline column-level CHECK constraint with no explicit
 *    name (what typeEnum() above generates) gets Postgres's own default
 *    auto-generated name, "{table}_{column}_check" — a stable, documented
 *    Postgres convention, not a Laravel implementation detail.
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

        $this->setStatusCheck($table, ['pending', 'ingested', 'queued', 'failed'], 'pending');
    }

    public function down(): void
    {
        $table = $this->resolveTable();
        if (!$table) {
            return;
        }

        $this->setStatusCheck($table, ['pending', 'ingested', 'failed'], 'pending');
    }

    private function setStatusCheck(string $table, array $values, string $default): void
    {
        $driver = DB::getDriverName();
        $list   = "'" . implode("','", $values) . "'";

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE {$table} MODIFY status ENUM({$list}) NOT NULL DEFAULT '{$default}'");
            return;
        }

        if ($driver === 'pgsql') {
            $constraint = "{$table}_status_check";
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint}");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK (status IN ({$list}))");
            return;
        }

        if ($driver === 'sqlite') {
            try {
                Schema::table($table, function (Blueprint $t) use ($values, $default) {
                    $t->enum('status', $values)->default($default)->change();
                });
            } catch (\Throwable $e) {
                // Genuinely no other portable path on an old SQLite/Laravel
                // combination that can't rebuild the table — degrades to a
                // no-op rather than breaking the migration; 'queued' would
                // then only be rejected by that specific install's DB
                // layer, not by anything in this package (ProjectFile's
                // status is a plain string attribute, not a PHP-level enum).
            }
        }
    }
};
