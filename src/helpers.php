<?php

use EasyAI\LaravelAI\Facades\AI;

if (!function_exists('ai')) {
    /**
     * Quick AI chat helper.
     *
     * @param  string      $message   The user message
     * @param  string|null $provider  Provider name (null = default)
     * @param  string|null $model     Model override
     * @return string                 AI response content
     */
    function ai(string $message, ?string $provider = null, ?string $model = null): string
    {
        $driver = AI::provider($provider);

        if ($model) {
            $driver = $driver->model($model);
        }

        return $driver->chat([
            ['role' => 'user', 'content' => $message],
        ])->getContent();
    }
}
