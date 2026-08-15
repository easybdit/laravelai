<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A public, unguessable token that (when set) makes GET ai-chat/s/{token}
 * render this session's conversation as a read-only page — no login, no
 * ownership check at all, deliberately, since the entire point is letting
 * someone with no account view a shared link. Distinct from guest_token,
 * which identifies the guest's own browser for ownership checks and is
 * never meant to be shared with anyone. Null by default: a session stays
 * exactly as private as it's always been until its owner explicitly
 * generates a link (AIChatController::shareLink()).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('ai_chat_sessions', 'share_token')) {
            return;
        }

        Schema::table('ai_chat_sessions', function (Blueprint $table) {
            $table->string('share_token', 40)->nullable()->unique()->after('guest_token');
        });
    }

    public function down(): void
    {
        Schema::table('ai_chat_sessions', function (Blueprint $table) {
            $table->dropUnique(['share_token']);
            $table->dropColumn('share_token');
        });
    }
};
