<?php

namespace EasyAI\LaravelAI\Chat;

use EasyAI\LaravelAI\Chat\Support\SettingsOverlay;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ChatServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Any admin-saved Settings-page overrides win over config()/.env
        // for this request — no-ops entirely until something's been saved.
        SettingsOverlay::apply();

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
