<?php

namespace EasyAI\LaravelAI\Tests\Unit;

use EasyAI\LaravelAI\Agent\Contracts\WebSearchProvider;
use EasyAI\LaravelAI\Agent\Tool;
use EasyAI\LaravelAI\Agent\Tools\WebSearchTool;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class WebSearchToolTest extends TestCase
{
    public function test_make_returns_a_web_search_tool(): void
    {
        $tool = WebSearchTool::make();

        $this->assertInstanceOf(Tool::class, $tool);
        $this->assertSame('web_search', $tool->name);
        $this->assertNotEmpty($tool->description);
        $this->assertSame(['query'], $tool->parameters['required']);
    }

    public function test_execute_returns_mapped_results_from_configured_provider(): void
    {
        config(['ai.agent.web_search.provider' => 'tavily']);
        config(['ai.agent.web_search.tavily.api_key' => 'test-tavily-key']);

        Http::fake([
            'api.tavily.com/*' => Http::response([
                'results' => [
                    ['title' => 'Laravel', 'url' => 'https://laravel.com', 'content' => 'The PHP Framework'],
                ],
            ], 200),
        ]);

        $tool = WebSearchTool::make();
        $result = $tool->execute(['query' => 'test']);

        $this->assertSame([
            'results' => [
                ['title' => 'Laravel', 'url' => 'https://laravel.com', 'snippet' => 'The PHP Framework'],
            ],
        ], $result);
    }

    public function test_execute_returns_not_configured_shape_when_no_api_key_set(): void
    {
        config(['ai.agent.web_search.provider' => 'tavily']);
        config(['ai.agent.web_search.tavily.api_key' => null]);

        Http::fake();

        $tool = WebSearchTool::make();
        $result = $tool->execute(['query' => 'test']);

        $this->assertSame([], $result['results']);
        $this->assertArrayHasKey('note', $result);
        Http::assertNothingSent();
    }

    public function test_a_host_app_bound_provider_takes_priority(): void
    {
        $fake = new class implements WebSearchProvider {
            public function search(string $query, int $limit = 5): array
            {
                return [['title' => 'Bound', 'url' => 'https://example.com', 'snippet' => 'From the bound provider']];
            }
        };

        $this->app->bind(WebSearchProvider::class, fn () => $fake);

        $tool = WebSearchTool::make();
        $result = $tool->execute(['query' => 'test']);

        $this->assertSame([
            'results' => [
                ['title' => 'Bound', 'url' => 'https://example.com', 'snippet' => 'From the bound provider'],
            ],
        ], $result);
    }
}
