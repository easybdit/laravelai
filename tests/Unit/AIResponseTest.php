<?php

namespace EasyAI\LaravelAI\Tests\Unit;

use EasyAI\LaravelAI\Response\AIResponse;
use PHPUnit\Framework\TestCase;

class AIResponseTest extends TestCase
{
    public function test_response_getters(): void
    {
        $r = new AIResponse('Hello', 10, 5, 'llama3', 'ollama', ['raw' => true]);

        $this->assertEquals('Hello', $r->getContent());
        $this->assertEquals(10, $r->getPromptTokens());
        $this->assertEquals(5, $r->getCompletionTokens());
        $this->assertEquals(15, $r->getTotalTokens());
        $this->assertEquals('llama3', $r->getModel());
        $this->assertEquals('ollama', $r->getProvider());
        $this->assertEquals(['raw' => true], $r->getRaw());
    }

    public function test_response_magic_get(): void
    {
        $r = new AIResponse('Hi', 10, 5, 'gpt-4o', 'openai');

        $this->assertEquals('Hi', $r->content);
        $this->assertEquals(15, $r->totalTokens);
        $this->assertEquals('openai', $r->provider);
    }

    public function test_response_to_array(): void
    {
        $r = new AIResponse('Test', 8, 4, 'claude', 'anthropic');
        $arr = $r->toArray();

        $this->assertEquals('Test', $arr['content']);
        $this->assertEquals(12, $arr['total_tokens']);
        $this->assertEquals('anthropic', $arr['provider']);
    }

    public function test_response_to_string(): void
    {
        $r = new AIResponse('World', 0, 0, 'x', 'y');
        $this->assertEquals('World', (string) $r);
    }
}
