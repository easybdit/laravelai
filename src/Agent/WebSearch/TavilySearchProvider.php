<?php

namespace EasyAI\LaravelAI\Agent\WebSearch;

use EasyAI\LaravelAI\Agent\Contracts\WebSearchProvider;
use Illuminate\Support\Facades\Http;

/**
 * Default web search provider, backed by the Tavily Search API
 * (https://api.tavily.com/search). Chosen as this package's default
 * because it has a genuinely free tier (1000 searches/month, no credit
 * card) and is purpose-built for AI-agent tool use — it returns clean
 * title/url/content results rather than raw HTML/SERP scraping.
 *
 * Configure via config/ai.php (or the underlying env vars):
 *
 *   'ai.agent.web_search.tavily.api_key' => env('TAVILY_API_KEY'),
 *   'ai.agent.web_search.tavily.url'     => env('TAVILY_API_URL', 'https://api.tavily.com/search'),
 *
 * With no API key configured, search() returns [] without making a
 * request — this is a valid "not configured" state, not an error.
 */
class TavilySearchProvider implements WebSearchProvider
{
    public function __construct(
        private ?string $apiKey = null,
        private string $url = 'https://api.tavily.com/search',
    ) {
        $this->apiKey ??= config('ai.agent.web_search.tavily.api_key');
        $this->url = config('ai.agent.web_search.tavily.url', $this->url);
    }

    public function search(string $query, int $limit = 5): array
    {
        if (!$this->apiKey) {
            return [];
        }

        try {
            $response = Http::timeout(15)->post($this->url, [
                'api_key'       => $this->apiKey,
                'query'         => $query,
                'max_results'   => max(1, $limit),
                'search_depth'  => 'basic',
            ]);

            if (!$response->successful()) {
                return [];
            }

            return collect($response->json('results', []))
                ->map(fn ($result) => [
                    'title'   => $result['title'] ?? '',
                    'url'     => $result['url'] ?? '',
                    'snippet' => $result['content'] ?? '',
                ])
                ->filter(fn ($result) => $result['url'] !== '')
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
