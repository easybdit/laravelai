<?php

namespace EasyAI\LaravelAI\Tests\Unit;

use EasyAI\LaravelAI\Agent\WebSearch\TavilySearchProvider;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class TavilySearchProviderTest extends TestCase
{
    public function test_successful_response_maps_to_title_url_snippet(): void
    {
        config(['ai.agent.web_search.tavily.api_key' => 'test-tavily-key']);

        Http::fake([
            'api.tavily.com/*' => Http::response([
                'results' => [
                    ['title' => 'Laravel', 'url' => 'https://laravel.com', 'content' => 'The PHP Framework'],
                    ['title' => 'PHP', 'url' => 'https://php.net', 'content' => 'The PHP language'],
                ],
            ], 200),
        ]);

        $provider = new TavilySearchProvider();
        $results = $provider->search('laravel framework', 5);

        $this->assertCount(2, $results);
        $this->assertSame([
            'title'   => 'Laravel',
            'url'     => 'https://laravel.com',
            'snippet' => 'The PHP Framework',
        ], $results[0]);
        $this->assertSame([
            'title'   => 'PHP',
            'url'     => 'https://php.net',
            'snippet' => 'The PHP language',
        ], $results[1]);
    }

    public function test_missing_api_key_returns_empty_without_http_call(): void
    {
        config(['ai.agent.web_search.tavily.api_key' => null]);

        Http::fake();

        $provider = new TavilySearchProvider();
        $results = $provider->search('anything', 5);

        $this->assertSame([], $results);
        Http::assertNothingSent();
    }

    public function test_http_failure_returns_empty_without_throwing(): void
    {
        config(['ai.agent.web_search.tavily.api_key' => 'test-tavily-key']);

        Http::fake([
            'api.tavily.com/*' => Http::response('', 500),
        ]);

        $provider = new TavilySearchProvider();
        $results = $provider->search('anything', 5);

        $this->assertSame([], $results);
    }
}
