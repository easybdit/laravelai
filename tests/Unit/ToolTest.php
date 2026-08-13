<?php

namespace EasyAI\LaravelAI\Tests\Unit;

use EasyAI\LaravelAI\Agent\Tool;
use PHPUnit\Framework\TestCase;

class ToolTest extends TestCase
{
    public function test_make_builds_a_tool_with_a_closure_handler(): void
    {
        $tool = Tool::make(
            'get_weather',
            'Gets the current weather for a city.',
            ['type' => 'object', 'properties' => ['city' => ['type' => 'string']], 'required' => ['city']],
            fn (array $args) => "Sunny in {$args['city']}"
        );

        $this->assertSame('get_weather', $tool->name);
        $this->assertSame('Gets the current weather for a city.', $tool->description);
        $this->assertSame(['city' => ['type' => 'string']], $tool->parameters['properties']);
        $this->assertInstanceOf(\Closure::class, $tool->handler);
    }

    public function test_execute_runs_the_handler_and_returns_its_result(): void
    {
        $tool = Tool::make('add', 'Adds two numbers', [], fn (array $args) => $args['a'] + $args['b']);

        $this->assertSame(7, $tool->execute(['a' => 3, 'b' => 4]));
    }

    public function test_execute_catches_exceptions_into_an_error_array(): void
    {
        $tool = Tool::make('boom', 'Always fails', [], function (array $args) {
            throw new \RuntimeException('kaboom');
        });

        $result = $tool->execute([]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertSame('kaboom', $result['error']);
    }

    public function test_execute_catches_any_throwable_not_just_exceptions(): void
    {
        $tool = Tool::make('type_error', 'Triggers a TypeError', [], function (array $args) {
            // Calling a non-callable triggers a \Error (a \Throwable, not an \Exception)
            $notCallable = null;
            return $notCallable();
        });

        $result = $tool->execute([]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
    }
}
