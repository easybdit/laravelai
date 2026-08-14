<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An append-only ledger of every AI call this package's drivers make —
 * chat completions and image generation alike — written by UsageLogger and
 * read by the Settings page's "Usage & Costs" card. Opt-in and off by
 * default (config('ai.usage_logging.enabled')): a fresh install that never
 * turns this on never writes a row here, same additive posture as
 * ai_settings/ai_admins.
 *
 * Not scoped to the bundled chat UI: any AI::provider(...)->chat()/
 * generateImage() call anywhere in the host app is logged here once
 * enabled, not just ones that went through /ai-chat. user_id is stored the
 * same intentionally-loose way as chat_sessions.user_id (no foreign key,
 * no assumption about the host app's users table) for the same reason.
 *
 * estimated_cost is nullable on purpose — UsageLogger only ever fills it
 * in when config('ai.pricing.*') has a real rate for that exact
 * provider/model; a null here means "no rate configured," never "free."
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_usage_logs')) {
            return;
        }

        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50);
            $table->string('model', 150);
            $table->string('kind', 20); // chat | image | embed
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('image_count')->default(0);
            $table->decimal('estimated_cost', 10, 6)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('guest_token', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['provider', 'created_at']);
            $table->index('kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
