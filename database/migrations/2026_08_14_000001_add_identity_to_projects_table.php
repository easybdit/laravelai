<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projects previously had no owner at all — any visitor (authenticated or
 * guest) could list, read, or delete *any* project on the install, since
 * ProjectController never had anything to scope against. This mirrors the
 * identity columns chat_sessions already got in
 * 2026_08_13_000000_add_identity_to_chat_sessions_table.php, including the
 * same upgrade-safety rule: a project with no identity recorded (created
 * before this migration) stays visible to everyone rather than vanishing
 * or becoming inaccessible on upgrade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id')->index();
            }
            if (!Schema::hasColumn('projects', 'guest_token')) {
                $table->string('guest_token', 64)->nullable()->after('user_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            foreach (['user_id', 'guest_token'] as $col) {
                if (Schema::hasColumn('projects', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
