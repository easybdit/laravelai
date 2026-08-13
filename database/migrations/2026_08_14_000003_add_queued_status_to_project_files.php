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
 *    PostgresGrammar::typeEnum()) — but unlike SQLite, ->change() here was
 *    found live (real GitHub Actions Postgres service container, not a
 *    guess) to silently fail to actually widen the constraint: a
 *    dedicated regression test asserting a 'queued' status row can be
 *    created got a 500 (a QueryException, confirmed with
 *    withoutExceptionHandling() during debugging) instead of 201. A first
 *    attempt at fixing this with direct DROP/ADD CONSTRAINT raw SQL,
 *    guessing Postgres's default auto-generated constraint name
 *    ("{table}_{column}_check") was *also* found live to fail the exact
 *    same way — DROP CONSTRAINT IF EXISTS on a guessed name that doesn't
 *    match the real one is a silent no-op, so the untouched original
 *    constraint kept rejecting 'queued' right alongside the new
 *    (correct, but redundant) constraint this added — Postgres enforces
 *    every CHECK constraint on a column simultaneously, not just the
 *    newest one. Fixed for real by looking the actual constraint name up
 *    from pg_constraint instead of assuming it.
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
            // Don't guess Postgres's default constraint-naming convention —
            // an earlier version of this migration assumed
            // "{table}_status_check" and was wrong (or wrong on some PG
            // version/config this wasn't tested against): DROP CONSTRAINT
            // IF EXISTS on a name that doesn't match is a silent no-op, so
            // the OLD constraint stayed in place right alongside the new
            // one this added — and Postgres enforces every CHECK
            // constraint on a column simultaneously, so 'queued' kept
            // getting rejected by the untouched original regardless of
            // what got added. Found live via a real Postgres service
            // container after the name-guessing version still failed the
            // exact same way. Look up every actual CHECK constraint on
            // this table from the system catalog and drop all of them —
            // there's only ever the one on this table — instead of
            // assuming what it's called.
            $constraints = DB::select(
                "SELECT con.conname FROM pg_constraint con
                 JOIN pg_class rel ON rel.oid = con.conrelid
                 WHERE rel.relname = ? AND con.contype = 'c'",
                [$table]
            );

            foreach ($constraints as $c) {
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT \"{$c->conname}\"");
            }

            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_status_check CHECK (status IN ({$list}))");
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
