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

    public function test_with_image_builds_universal_multipart_content(): void
    {
        $content = MessageFormatter::withImage('Describe this', 'base64data', 'image/png');

        $this->assertSame(['type' => 'text', 'text' => 'Describe this'], $content[0]);
        $this->assertSame(['type' => 'image', 'mime' => 'image/png', 'data' => 'base64data'], $content[1]);
    }

    public function test_to_provider_content_translates_for_openai(): void
    {
        $messages = [['role' => 'user', 'content' => MessageFormatter::withImage('What is this?', 'abc', 'image/png')]];
        $result   = MessageFormatter::toProviderContent($messages, 'openai');

        $this->assertSame('text', $result[0]['content'][0]['type']);
        $this->assertSame('What is this?', $result[0]['content'][0]['text']);
        $this->assertSame('image_url', $result[0]['content'][1]['type']);
        $this->assertSame('data:image/png;base64,abc', $result[0]['content'][1]['image_url']['url']);
    }

    public function test_to_provider_content_translates_for_anthropic(): void
    {
        $messages = [['role' => 'user', 'content' => MessageFormatter::withImage('What is this?', 'abc', 'image/png')]];
        $result   = MessageFormatter::toProviderContent($messages, 'anthropic');

        $this->assertSame('image', $result[0]['content'][1]['type']);
        $this->assertSame('base64', $result[0]['content'][1]['source']['type']);
        $this->assertSame('image/png', $result[0]['content'][1]['source']['media_type']);
        $this->assertSame('abc', $result[0]['content'][1]['source']['data']);
    }

    public function test_to_provider_content_translates_for_gemini(): void
    {
        $messages = [['role' => 'user', 'content' => MessageFormatter::withImage('What is this?', 'abc', 'image/png')]];
        $result   = MessageFormatter::toProviderContent($messages, 'gemini');

        $this->assertSame(['text' => 'What is this?'], $result[0]['content'][0]);
        $this->assertSame(['inline_data' => ['mime_type' => 'image/png', 'data' => 'abc']], $result[0]['content'][1]);
    }

    public function test_to_provider_content_leaves_plain_string_messages_untouched(): void
    {
        $messages = [['role' => 'user', 'content' => 'Hello']];
        $result   = MessageFormatter::toProviderContent($messages, 'openai');

        $this->assertSame('Hello', $result[0]['content']);
    }

    public function test_anthropic_does_not_merge_multipart_content_as_strings(): void
    {
        // A vision message immediately preceded by another user-role message
        // (rare, but possible) must not be string-concatenated with '.=' —
        // that would throw since content is an array, not a string.
        $messages = [
            ['role' => 'user', 'content' => 'Earlier note'],
            ['role' => 'user', 'content' => MessageFormatter::withImage('Look at this', 'abc', 'image/png')],
        ];
        $result = MessageFormatter::normalize($messages, 'anthropic');

        $this->assertCount(2, $result['messages']);
        $this->assertIsArray($result['messages'][1]['content']);
    }
}
