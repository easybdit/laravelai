<?php

namespace EasyAI\LaravelAI\Tests\Unit;

use EasyAI\LaravelAI\Commerce\Support\StructuredResponseParser;
use PHPUnit\Framework\TestCase;

class StructuredResponseParserTest extends TestCase
{
    public function test_extracts_query_block_and_strips_it_from_text(): void
    {
        $response = 'Let me check that for you.' . "\n"
            . '{"query":{"type":"revenue","from":"2026-07-01","to":"2026-07-31"}}';

        $result = StructuredResponseParser::extract($response, 'query');

        $this->assertSame(['type' => 'revenue', 'from' => '2026-07-01', 'to' => '2026-07-31'], $result['data']);
        $this->assertSame('Let me check that for you.', $result['text']);
    }

    public function test_returns_null_data_when_no_block_present(): void
    {
        $result = StructuredResponseParser::extract('Just a normal reply, no data needed.', 'query');

        $this->assertNull($result['data']);
        $this->assertSame('Just a normal reply, no data needed.', $result['text']);
    }

    public function test_different_keys_do_not_cross_match(): void
    {
        $response = '{"search":{"keyword":"red dress"}}';

        $this->assertNull(StructuredResponseParser::extract($response, 'query')['data']);
        $this->assertSame(['keyword' => 'red dress'], StructuredResponseParser::extract($response, 'search')['data']);
    }

    public function test_malformed_block_is_ignored_not_fatal(): void
    {
        $result = StructuredResponseParser::extract('here is {"query": not valid json}', 'query');

        $this->assertNull($result['data']);
    }
}
