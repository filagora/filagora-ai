<?php

namespace Filagora\Ai\Tests\Unit;

use Filagora\Ai\Providers\AnthropicChatProvider;
use Filagora\Ai\Tests\TestCase;
use ReflectionMethod;

/**
 * Anthropic's Messages API streaming SSE frames have no live endpoint to
 * verify against in this environment, so this exercises the hand-written
 * parser directly against a realistic recorded frame sequence (shape per
 * https://docs.anthropic.com/en/api/messages-streaming) via reflection,
 * since handleLine()/finalUsage() are protected.
 */
class AnthropicChatProviderParsingTest extends TestCase
{
    public function test_parses_text_deltas_and_final_token_usage_from_a_realistic_stream(): void
    {
        $provider = new AnthropicChatProvider;
        $handleLine = new ReflectionMethod($provider, 'handleLine');
        $finalUsage = new ReflectionMethod($provider, 'finalUsage');

        $lines = [
            'event: message_start',
            'data: {"type":"message_start","message":{"id":"msg_1","usage":{"input_tokens":42,"output_tokens":0}}}',
            '',
            'event: content_block_start',
            'data: {"type":"content_block_start","index":0,"content_block":{"type":"text","text":""}}',
            '',
            'event: ping',
            'data: {"type":"ping"}',
            '',
            'event: content_block_delta',
            'data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":"Hello"}}',
            '',
            'event: content_block_delta',
            'data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":" world"}}',
            '',
            'event: content_block_stop',
            'data: {"type":"content_block_stop","index":0}',
            '',
            'event: message_delta',
            'data: {"type":"message_delta","delta":{"stop_reason":"end_turn"},"usage":{"output_tokens":2}}',
            '',
            'event: message_stop',
            'data: {"type":"message_stop"}',
        ];

        $assembled = '';
        foreach ($lines as $line) {
            $assembled .= (string) $handleLine->invoke($provider, $line);
        }

        $this->assertSame('Hello world', $assembled);
        $this->assertSame([42, 2], $finalUsage->invoke($provider));
    }

    public function test_ignores_non_text_delta_events(): void
    {
        $provider = new AnthropicChatProvider;
        $handleLine = new ReflectionMethod($provider, 'handleLine');

        $delta = $handleLine->invoke($provider, 'event: content_block_delta');
        $this->assertNull($delta);

        $delta = $handleLine->invoke($provider, 'data: {"type":"content_block_delta","index":0,"delta":{"type":"input_json_delta","partial_json":"{}"}}');
        $this->assertNull($delta);
    }
}
