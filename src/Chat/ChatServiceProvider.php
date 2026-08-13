<?php

namespace EasyAI\LaravelAI\Chat;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ChatServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Routes need the 'web' group explicitly — loadRoutesFrom() alone
        // doesn't apply it, and this chat depends on sessions (provider
        // switching), cookies (guest identity), and CSRF verification.
        Route::middleware('web')->group(__DIR__ . '/../../routes/chat.php');

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
