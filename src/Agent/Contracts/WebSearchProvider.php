<?php

namespace EasyAI\LaravelAI\Agent\Contracts;

/**
 * Bind your own implementation of this in your app's service provider:
 *
 *   $this->app->bind(WebSearchProvider::class, \App\Services\MySearchProvider::class);
 *
 * This package ships two default implementations (Tavily, Brave — see
 * src/Agent/WebSearch/) but never hardcodes either into the agent loop
 * itself: the {@see \EasyAI\LaravelAI\Agent\Tools\WebSearchTool} factory
 * resolves whichever implementation is bound, or falls back to a
 * config-selected default. This lets a host app swap in its own API key,
 * its preferred vendor, a self-hosted SearXNG instance, or a mocked
 * provider in tests, without this package ever knowing the difference.
 */
interface WebSearchProvider
{
    /**
     * Run a web search and return a normalized list of results.
     *
     * Implementors MUST NOT let network/API failures escape as exceptions —
     * missing API key, timeout, non-2xx response, malformed payload, etc.
     * should all degrade to an empty array rather than throwing. A web
     * search is one tool call inside a larger agent loop; a failure here
     * should read to the model as "no results", not blow up the whole run.
     * (Note: {@see \EasyAI\LaravelAI\Agent\Tool::execute()} already catches
     * any \Throwable a handler raises, so throwing is technically safe too —
     * but returning [] is friendlier: it avoids the handler wrapping the
     * failure in a generic {"error": "..."} shape and lets the tool
     * present a clearer "no results found" message to the model instead.)
     *
     * @param  string $query The search query, verbatim from the model.
     * @param  int    $limit Maximum number of results to return (best-effort —
     *                       an implementation may return fewer, never throw
     *                       for asking too many).
     * @return array<int, array{title: string, url: string, snippet: string}>
     */
    public function search(string $query, int $limit = 5): array;
}
