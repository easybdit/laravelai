<?php

namespace EasyAI\LaravelAI\Chat;

use EasyAI\LaravelAI\Chat\Models\AiAdmin;
use EasyAI\LaravelAI\Chat\Support\SettingsOverlay;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class ChatServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Any admin-saved Settings-page overrides win over config()/.env
        // for this request — no-ops entirely until something's been saved.
        SettingsOverlay::apply();

        // Zero-config default for config('ai.chat.settings_gate') — checks
        // the ai_admins table (see php artisan laravelai:make-admin) so a
        // fresh install needs no hand-written Gate::define() at all. Fully
        // overridable: Laravel boots the host app's own AppServiceProvider
        // after every package provider, so a real Gate::define() call
        // there for the same ability name simply replaces this one — this
        // never fights a host app's own role/permission system, it's only
        // a fallback for when nothing else has claimed the ability.
        // Schema::hasTable() guards the sliver of time between a fresh
        // `composer require` and the first `migrate` — false (fail-closed,
        // matching this page's own existing philosophy), not a raw
        // QueryException, if checked before the table exists.
        Gate::define(config('ai.chat.settings_gate', 'manage-ai-settings'), function ($user) {
            return Schema::hasTable('ai_admins')
                && AiAdmin::where('user_id', $user->getAuthIdentifier())->exists();
        });

        // Routes need the 'web' group explicitly — loadRoutesFrom() alone
        // doesn't apply it, and this chat depends on sessions (provider
        // switching), cookies (guest identity), and CSRF verification.
        // config('ai.chat.middleware') is empty (public) by default — add
        // e.g. AI_CHAT_MIDDLEWARE=auth to require login for the whole
        // /ai-chat area instead of just gating individual actions.
        Route::middleware(array_merge(['web'], config('ai.chat.middleware', [])))
            ->group(__DIR__ . '/../../routes/chat.php');

        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'laravelai');
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../resources/views' => resource_path('views/vendor/laravelai'),
            ], 'ai-chat-views');

            $this->publishes([
                __DIR__ . '/../../public' => public_path('vendor/laravelai'),
            ], 'ai-chat-assets');
        }
    }
}
