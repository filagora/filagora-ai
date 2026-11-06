<?php

namespace Filagora\Ai\Tests\Unit;

use Filagora\Ai\Providers\OpenAiChatProvider;
use Filagora\Ai\Tests\TestCase;
use ReflectionMethod;

/**
 * Exercises the shared OpenAI Chat Completions streaming parser (also used
 * by DigitalOceanChatProvider) directly against a realistic recorded frame
 * sequence, since handleLine()/finalUsage() are protected.
 */
class OpenAiCompatibleChatProviderParsingTest extends TestCase
{
    public function test_parses_text_deltas_and_final_token_usage_from_a_realistic_stream(): void
    {
        $provider = new OpenAiChatProvider;
        $handleLine = new ReflectionMethod($provider, 'handleLine');
        $finalUsage = new ReflectionMethod($provider, 'finalUsage');

        $lines = [
            'data: {"id":"1","choices":[{"index":0,"delta":{"role":"assistant"}}]}',
            'data: {"id":"1","choices":[{"index":0,"delta":{"content":"Hello"}}]}',
            'data: {"id":"1","choices":[{"index":0,"delta":{"content":" world"}}]}',
            'data: {"id":"1","choices":[],"usage":{"prompt_tokens":10,"completion_tokens":2,"total_tokens":12}}',
            'data: [DONE]',
        ];

        $assembled = '';
        foreach ($lines as $line) {
            $assembled .= (string) $handleLine->invoke($provider, $line);
        }

        $this->assertSame('Hello world', $assembled);
        $this->assertSame([10, 2], $finalUsage->invoke($provider));
    }

    public function test_ignores_non_data_lines_and_the_done_sentinel(): void
    {
        $provider = new OpenAiChatProvider;
        $handleLine = new ReflectionMethod($provider, 'handleLine');

        $this->assertNull($handleLine->invoke($provider, ''));
        $this->assertNull($handleLine->invoke($provider, 'event: ping'));
        $this->assertNull($handleLine->invoke($provider, 'data: [DONE]'));
    }
}
