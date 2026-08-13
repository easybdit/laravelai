<?php

namespace EasyAI\LaravelAI\RAG;

use EasyAI\LaravelAI\Facades\AI;
use Illuminate\Support\Facades\DB;

/**
 * The Laravel equivalent of the WordPress plugin's "Ask This Site" —
 * keeps RAG in sync with the host app's own Eloquent models instead of
 * WordPress posts/pages, driven entirely by config('ai.rag.auto_index')
 * (empty/off by default, since there's no universal "content type" to
 * default to the way WordPress has posts).
 *
 * Each entry maps a model to a RAG source, a text builder, and an
 * optional "should this row even be indexed" gate:
 *
 *   'posts' => [
 *       'model'  => \App\Models\Post::class,
 *       'source' => 'site_posts',
 *       'text'   => fn ($post) => $post->title . "\n\n" . strip_tags($post->body),
 *       'when'   => fn ($post) => $post->is_published,
 *   ],
 */
class AutoIndexer
{
    public static function boot(): void
    {
        foreach (config('ai.rag.auto_index', []) as $key => $entry) {
            self::registerObservers($key, $entry);
        }
    }

    private static function registerObservers(string $key, array $entry): void
    {
        $modelClass = $entry['model'] ?? null;
        if (!$modelClass || !class_exists($modelClass)) {
            return;
        }

        $source = $entry['source'] ?? $key;
        $textFn = $entry['text'] ?? null;
        $whenFn = $entry['when'] ?? null;

        if (!is_callable($textFn)) {
            return;
        }

        $modelClass::saved(function ($model) use ($source, $textFn, $whenFn) {
            self::reindex($model, $source, $textFn, $whenFn);
        });

        $modelClass::deleted(function ($model) use ($source) {
            self::deindex($model, $source);
        });
    }

    private static function reindex($model, string $source, callable $textFn, ?callable $whenFn): void
    {
        if ($whenFn && !$whenFn($model)) {
            self::deindex($model, $source);
            return;
        }

        try {
            $text = trim((string) $textFn($model));
            if ($text === '') {
                return;
            }

            self::deindex($model, $source);
            AI::rag()->ingest($text, $source . ':' . $model->getKey());
        } catch (\Throwable) {
            // Never let auto-indexing break a model save.
        }
    }

    private static function deindex($model, string $source): void
    {
        try {
            DB::table(config('ai.rag.table', 'ai_documents'))
                ->where('source', $source . ':' . $model->getKey())
                ->delete();
        } catch (\Throwable) {
            // Table may not exist yet on a fresh install — non-fatal.
        }
    }
}
