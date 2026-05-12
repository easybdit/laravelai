<?php

namespace EasyAI\LaravelAI\Tests\Unit;

use EasyAI\LaravelAI\Support\MessageFormatter;
use PHPUnit\Framework\TestCase;

class MessageFormatterTest extends TestCase
{
    public function test_openai_passthrough(): void
    {
        $messages = [
            ['role' => 'system', 'content' => 'Be helpful'],
            ['role' => 'user', 'content' => 'Hi'],
        ];
        $result = MessageFormatter::normalize($messages, 'openai');

        $this->assertNull($result['system']);
        $this->assertCount(2, $result['messages']);
    }

    public function test_anthropic_extracts_system(): void
    {
        $messages = [
            ['role' => 'system', 'content' => 'Be helpful'],
            ['role' => 'user', 'content' => 'Hi'],
        ];
        $result = MessageFormatter::normalize($messages, 'anthropic');

        $this->assertEquals('Be helpful', $result['system']);
        $this->assertCount(1, $result['messages']);
        $this->assertEquals('user', $result['messages'][0]['role']);
    }

    public function test_anthropic_merges_consecutive_roles(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'user', 'content' => 'Are you there?'],
        ];
        $result = MessageFormatter::normalize($messages, 'anthropic');

        $this->assertCount(1, $result['messages']);
        $this->assertStringContainsString('Hello', $result['messages'][0]['content']);
        $this->assertStringContainsString('Are you there?', $result['messages'][0]['content']);
    }

    public function test_anthropic_prepends_user_if_first_is_assistant(): void
    {
        $messages = [
            ['role' => 'assistant', 'content' => 'Previous response'],
        ];
        $result = MessageFormatter::normalize($messages, 'anthropic');

        $this->assertEquals('user', $result['messages'][0]['role']);
    }
}
