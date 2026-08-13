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

        // Schema-agnostic commerce assistants — binds no resolvers, creates
        // no tables; every endpoint is a clear 501 until the host app binds
        // its own Order/Product/StoreAnalytics resolver.
        $this->app->register(\EasyAI\LaravelAI\Commerce\CommerceServiceProvider::class);

        // Opt-in "Ask This Site" — no-op unless config('ai.rag.auto_index') lists models.
        \EasyAI\LaravelAI\RAG\AutoIndexer::boot();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/ai.php' => config_path('ai.php'),
            ], 'ai-config');

            $this->commands([
                \EasyAI\LaravelAI\Console\InstallCommand::class,
            ]);
        }
    }
}
