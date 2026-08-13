<?php

namespace EasyAI\LaravelAI;

use Illuminate\Support\ServiceProvider;

class AIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ai.php', 'ai');

        $this->app->singleton('laravel-ai', function ($app) {
            return new AIManager($app);
        });

        $this->app->alias('laravel-ai', AIManager::class);
    }

    public function boot(): void
    {
        // Register chat UI subpackage
        $this->app->register(\EasyAI\LaravelAI\Chat\ChatServiceProvider::class);

        // Opt-in "Ask This Site" — no-op unless config('ai.rag.auto_index') lists models.
        \EasyAI\LaravelAI\RAG\AutoIndexer::boot();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/ai.php' => config_path('ai.php'),
            ], 'ai-config');
        }
    }
}
