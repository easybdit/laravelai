<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A generic key → value override store for anything normally read via
 * config('ai.*') — e.g. "ai.providers.openai.api_key", "ai.default".
 *
 * This does NOT replace config()/.env as the source of truth: it's an
 * opt-in overlay applied on top at boot (see SettingsOverlay). An app
 * that never touches the admin Settings page behaves exactly as before —
 * zero rows here means config() is untouched. Only keys an admin has
 * actually saved through the UI ever override their env-configured default.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_settings')) {
            Schema::create('ai_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
    }
};
