<?php

namespace EasyAI\LaravelAI\Tests\Unit;

use EasyAI\LaravelAI\Support\TokenEstimator;
use PHPUnit\Framework\TestCase;

class TokenEstimatorTest extends TestCase
{
    public function test_estimate_empty(): void
    {
        $this->assertEquals(0, TokenEstimator::estimate(''));
        $this->assertEquals(0, TokenEstimator::estimate('   '));
    }

    public function test_estimate_short_text(): void
    {
        $tokens = TokenEstimator::estimate('Hello world');
        $this->assertGreaterThan(0, $tokens);
        $this->assertLessThan(20, $tokens);
    }

    public function test_estimate_messages(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi there, how can I help?'],
        ];
        $tokens = TokenEstimator::estimateMessages($messages);
        $this->assertGreaterThan(10, $tokens);
    }
}
