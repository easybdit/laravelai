<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who can reach /ai-chat/settings — a scalable replacement for hand-writing
 * Gate::define('manage-ai-settings', ...) in the host app yourself. Stores
 * only a bare user_id (unsignedBigInteger, no foreign key), same
 * intentionally-loose pattern already used for chat_sessions.user_id
 * (2026_08_13_000000) and projects.user_id — this package never assumes
 * the host app's users table name, primary key type, or schema shape.
 *
 * Bootstrapping the first row is a genuine chicken-and-egg problem (the
 * UI that manages this table is itself gated by this table being
 * non-empty) — solved by `php artisan laravelai:make-admin {email}`,
 * a one-time CLI step. Every admin after the first can be added from the
 * Settings page itself once at least one exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_admins')) {
            return;
        }

        Schema::create('ai_admins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_admins');
    }
};
