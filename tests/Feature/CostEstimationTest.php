<?php

namespace EasyAI\LaravelAI\Tests\Feature;

use EasyAI\LaravelAI\Facades\AI;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Support\Facades\Http;

/**
 * AIResponse::getEstimatedCost() — deliberately null unless the caller
 * configures a real rate themselves (config('ai.pricing') ships empty; see
 * that key's own docblock for why this package won't guess prices).
 */
class CostEstimationTest extends TestCase
{
    public function test_cost_is_null_with_no_pricing_configured(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'hi']]],
                'usage'   => ['prompt_tokens' => 100, 'completion_tokens' => 50],
                'model'   => 'gpt-4o-mini',
            ]),
        ]);

        $response = AI::provider('openai')->model('gpt-4o-mini')->chat([['role' => 'user', 'content' => 'Hi']]);

        $this->assertNull($response->getEstimatedCost());
    }

    public function test_cost_is_computed_from_configured_rate(): void
    {
        config(['ai.pricing.openai.gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60]]); // $ per 1K tokens

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'hi']]],
                'usage'   => ['prompt_tokens' => 1000, 'completion_tokens' => 500],
                'model'   => 'gpt-4o-mini',
            ]),
        ]);

        $response = AI::provider('openai')->model('gpt-4o-mini')->chat([['role' => 'user', 'content' => 'Hi']]);

        // 1000/1000 * 0.15 + 500/1000 * 0.60 = 0.15 + 0.30 = 0.45
        $this->assertEqualsWithDelta(0.45, $response->getEstimatedCost(), 0.0001);
    }

    public function test_cost_is_null_for_a_different_model_than_the_one_configured(): void
    {
        config(['ai.pricing.openai.gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60]]);

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'hi']]],
                'usage'   => ['prompt_tokens' => 100, 'completion_tokens' => 50],
                'model'   => 'gpt-4o', // not the configured "gpt-4o-mini"
            ]),
        ]);

        $response = AI::provider('openai')->model('gpt-4o')->chat([['role' => 'user', 'content' => 'Hi']]);

        $this->assertNull($response->getEstimatedCost());
    }

    public function test_incomplete_rate_missing_output_key_is_treated_as_unconfigured(): void
    {
        config(['ai.pricing.openai.gpt-4o-mini' => ['input' => 0.15]]); // no "output"

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'hi']]],
                'usage'   => ['prompt_tokens' => 100, 'completion_tokens' => 50],
                'model'   => 'gpt-4o-mini',
            ]),
        ]);

        $response = AI::provider('openai')->model('gpt-4o-mini')->chat([['role' => 'user', 'content' => 'Hi']]);

        $this->assertNull($response->getEstimatedCost());
    }
}
