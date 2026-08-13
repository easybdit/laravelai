<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The bare table names "projects" / "project_files" (shipped since
 * v1.4.0) are a near-certain collision with any host app's own
 * "projects" concept — a genuinely common table name. An earlier attempt
 * at this fix edited the original 2026_08_06_000000 migration in place,
 * on the (wrong, by the time it mattered) assumption that the table had
 * never shipped in a tagged release yet. It had — v1.4.0 was already
 * public. Editing an already-ran migration's body does nothing for an
 * existing install (Laravel tracks migrations as "ran" by filename, not
 * content) — it would have created ai_projects for brand new installs
 * while silently leaving existing installs on a table the renamed
 * Project/ProjectFile models could no longer find at all.
 *
 * A real rename, guarded both ways, is safe for every install state:
 *  - Fresh install: the original migration already created "projects" /
 *    "project_files" moments ago in this same `migrate` run; this
 *    renames them immediately after.
 *  - Existing install (v1.4.0+): this renames the real table in place,
 *    preserving every row — the fix the original attempt should have
 *    been.
 *  - Already-upgraded install (unlikely but possible if the earlier,
 *    incorrect in-place edit was ever actually deployed somewhere):
 *    ai_projects already exists, so both guards below are no-ops.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('projects') && !Schema::hasTable('ai_projects')) {
            Schema::rename('projects', 'ai_projects');
        }

        if (Schema::hasTable('project_files') && !Schema::hasTable('ai_project_files')) {
            Schema::rename('project_files', 'ai_project_files');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_projects') && !Schema::hasTable('projects')) {
            Schema::rename('ai_projects', 'projects');
        }

        if (Schema::hasTable('ai_project_files') && !Schema::hasTable('project_files')) {
            Schema::rename('ai_project_files', 'project_files');
        }
    }
};
