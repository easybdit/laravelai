<?php

namespace EasyAI\LaravelAI\Agent\WebSearch;

use EasyAI\LaravelAI\Agent\Contracts\WebSearchProvider;
use Illuminate\Support\Facades\Http;

/**
 * Second built-in web search provider, backed by the Brave Search API
 * (https://api.search.brave.com/res/v1/web/search). Also has a free tier;
 * offered as an alternative to Tavily for users who already have a Brave
 * subscription token or prefer it.
 *
 * Configure via config/ai.php (or the underlying env vars):
 *
 *   'ai.agent.web_search.brave.api_key' => env('BRAVE_SEARCH_API_KEY'),
 *   'ai.agent.web_search.brave.url'     => env('BRAVE_SEARCH_API_URL', 'https://api.search.brave.com/res/v1/web/search'),
 *
 * With no API key configured, search() returns [] without making a
 * request — this is a valid "not configured" state, not an error.
 */
class BraveSearchProvider implements WebSearchProvider
{
    public function __construct(
        private ?string $apiKey = null,
        private string $url = 'https://api.search.brave.com/res/v1/web/search',
    ) {
        $this->apiKey ??= config('ai.agent.web_search.brave.api_key');
        $this->url = config('ai.agent.web_search.brave.url', $this->url);
    }

    public function search(string $query, int $limit = 5): array
    {
        if (!$this->apiKey) {
            return [];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Accept'                   => 'application/json',
                    'X-Subscription-Token'     => $this->apiKey,
                ])
                ->get($this->url, [
                    'q'     => $query,
                    'count' => max(1, $limit),
                ]);

            if (!$response->successful()) {
                return [];
            }

            return collect($response->json('web.results', []))
                ->map(fn ($result) => [
                    'title'   => $result['title'] ?? '',
                    'url'     => $result['url'] ?? '',
                    'snippet' => $result['description'] ?? '',
                ])
                ->filter(fn ($result) => $result['url'] !== '')
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
