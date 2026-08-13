<?php

namespace EasyAI\LaravelAI\Agent\Tools;

use EasyAI\LaravelAI\Agent\Contracts\WebSearchProvider;
use EasyAI\LaravelAI\Agent\Tool;
use EasyAI\LaravelAI\Agent\WebSearch\BraveSearchProvider;
use EasyAI\LaravelAI\Agent\WebSearch\TavilySearchProvider;

/**
 * Factory that wraps whichever {@see WebSearchProvider} is configured (or
 * bound in the container) into a ready-to-use {@see Tool}:
 *
 *   AI::provider('openai')->tools([WebSearchTool::make()])->run($messages);
 *
 * Provider resolution order:
 *   1. A host app's own binding — $this->app->bind(WebSearchProvider::class, ...)
 *   2. The built-in implementation selected by config('ai.agent.web_search.provider'),
 *      one of 'tavily' (default) or 'brave'.
 *
 * The handler never throws and never returns nothing — even "no results"
 * or "not configured" comes back as a small, model-readable payload, since
 * a tool call that silently vanishes confuses the model far more than a
 * clear "no results found" message would.
 */
class WebSearchTool
{
    public static function make(): Tool
    {
        return Tool::make(
            name: 'web_search',
            description: 'Search the web for current, up-to-date information not in your training data '
                . '— news, prices, recent events, documentation for a specific version, etc. '
                . 'Returns a short list of title/url/snippet results.',
            parameters: [
                'type'       => 'object',
                'properties' => [
                    'query' => [
                        'type'        => 'string',
                        'description' => 'The search query.',
                    ],
                ],
                'required' => ['query'],
            ],
            handler: function (array $arguments) {
                $provider = static::resolveProvider();
                $limit = (int) config('ai.agent.web_search.limit', 5);

                $results = $provider->search($arguments['query'] ?? '', $limit);

                if (empty($results)) {
                    return [
                        'results' => [],
                        'note'    => 'No results found, or web search is not configured.',
                    ];
                }

                return ['results' => $results];
            },
        );
    }

    /**
     * Resolves the WebSearchProvider to use: a host-app binding takes
     * priority over the built-in, config-selected default.
     */
    private static function resolveProvider(): WebSearchProvider
    {
        if (app()->bound(WebSearchProvider::class)) {
            return app(WebSearchProvider::class);
        }

        return match (config('ai.agent.web_search.provider', 'tavily')) {
            'brave' => new BraveSearchProvider(),
            default => new TavilySearchProvider(),
        };
    }
}
