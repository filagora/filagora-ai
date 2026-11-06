<?php

namespace Filagora\Ai\Tests\Feature;

use Filagora\Ai\Models\AiChatUsage;
use Filagora\Ai\Services\ChatUsageRecorder;
use Filagora\Ai\Tests\Support\TestUser;
use Filagora\Ai\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class AiChatUsageRecordingTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_a_successful_call_with_both_token_counts_and_an_estimated_cost(): void
    {
        config([
            'filagora-ai.chat.cost_per_million_tokens.anthropic.claude-sonnet-5.input' => 3.00,
            'filagora-ai.chat.cost_per_million_tokens.anthropic.claude-sonnet-5.output' => 15.00,
        ]);

        app(ChatUsageRecorder::class)->recordSuccess(
            'anthropic',
            'claude-sonnet-5',
            1000,
            500,
            null,
            'ai_chat_reply',
        );

        $usage = AiChatUsage::query()->first();
        $this->assertNotNull($usage);
        $this->assertSame('anthropic', $usage->provider);
        $this->assertSame('claude-sonnet-5', $usage->model);
        $this->assertSame(1000, $usage->input_tokens);
        $this->assertSame(500, $usage->output_tokens);
        $this->assertTrue($usage->succeeded);
        // (1000/1e6 * 3.00) + (500/1e6 * 15.00) = 0.003 + 0.0075 = 0.0105
        $this->assertEqualsWithDelta(0.0105, (float) $usage->estimated_cost_usd, 0.000001);
    }

    public function test_records_a_failed_call_with_no_tokens_or_cost(): void
    {
        app(ChatUsageRecorder::class)->recordFailure('openai', 'gpt-5', null, 'ai_chat_reply');

        $usage = AiChatUsage::query()->first();
        $this->assertNotNull($usage);
        $this->assertFalse($usage->succeeded);
        $this->assertNull($usage->input_tokens);
        $this->assertNull($usage->output_tokens);
        $this->assertNull($usage->estimated_cost_usd);
    }

    public function test_a_missing_cost_rate_is_costed_at_the_fallback_price_and_says_so(): void
    {
        config([
            'filagora-ai.chat.cost_per_million_tokens' => [],
            'filagora-ai.chat.fallback_cost_per_million_tokens' => ['input' => 20.0, 'output' => 100.0],
        ]);

        app(ChatUsageRecorder::class)->recordSuccess('anthropic', 'claude-sonnet-5', 1000, 500, null, 'ai_chat_reply');

        $usage = AiChatUsage::query()->firstOrFail();
        $this->assertSame(1000, $usage->input_tokens);
        // 0.02 + 0.05: never free, or it would not count against any limit.
        $this->assertEqualsWithDelta(0.07, (float) $usage->estimated_cost_usd, 0.000001);
        $this->assertSame('fallback_price', $usage->cost_basis);
    }

    public function test_a_call_with_no_token_counts_has_no_cost_and_says_so(): void
    {
        app(ChatUsageRecorder::class)->recordSuccess('anthropic', 'claude-sonnet-5', null, null, null, 'ai_chat_reply');

        $usage = AiChatUsage::query()->firstOrFail();
        $this->assertNull($usage->estimated_cost_usd);
        $this->assertSame('none', $usage->cost_basis);
    }

    public function test_usage_is_recorded_against_the_user_and_the_feature(): void
    {
        $user = TestUser::make();

        app(ChatUsageRecorder::class)->recordSuccess('anthropic', 'claude-sonnet-5', 10, 5, null, 'thread_assist', $user->id);
        app(ChatUsageRecorder::class)->recordFailure('anthropic', 'claude-sonnet-5', null, 'ai_chat_reply', $user->id);

        $this->assertSame(['thread_assist', 'ai_chat_reply'], AiChatUsage::query()->where('user_id', $user->id)->orderBy('id')->pluck('context')->all());
        $this->assertSame('reported', AiChatUsage::query()->where('context', 'thread_assist')->firstOrFail()->cost_basis);
    }

    public function test_the_usage_row_outlives_the_user(): void
    {
        $user = TestUser::make();
        app(ChatUsageRecorder::class)->recordSuccess('anthropic', 'claude-sonnet-5', 10, 5, null, 'ai_chat_reply', $user->id);

        $user->delete();

        $usage = AiChatUsage::query()->firstOrFail();
        $this->assertNull($usage->user_id);
        $this->assertNotNull($usage->estimated_cost_usd);
    }

    public function test_an_abandoned_call_is_costed_from_the_text_and_not_counted_as_a_success(): void
    {
        config([
            'filagora-ai.chat.cost_per_million_tokens.anthropic.claude-sonnet-5.input' => 3.00,
            'filagora-ai.chat.cost_per_million_tokens.anthropic.claude-sonnet-5.output' => 15.00,
        ]);

        app(ChatUsageRecorder::class)->recordAbandoned('anthropic', 'claude-sonnet-5', 3000, 300, null, 'ai_chat_reply');

        $usage = AiChatUsage::query()->firstOrFail();
        $this->assertFalse($usage->succeeded);
        $this->assertSame('estimated_tokens', $usage->cost_basis);
        $this->assertSame(1000, $usage->input_tokens);
        $this->assertSame(100, $usage->output_tokens);
        $this->assertEqualsWithDelta(0.0045, (float) $usage->estimated_cost_usd, 0.000001);
    }

    public function test_a_database_failure_recording_usage_is_swallowed_and_logged(): void
    {
        Log::shouldReceive('warning')->once()->withArgs(fn (string $message): bool => $message === 'Failed to record AI chat usage.');

        // Force AiChatUsage::create() to fail without mocking Eloquent itself.
        AiChatUsage::creating(fn () => throw new \RuntimeException('database is down'));

        app(ChatUsageRecorder::class)->recordSuccess('anthropic', 'claude-sonnet-5', 10, 5, null, 'ai_chat_reply');

        AiChatUsage::flushEventListeners();

        $this->assertSame(0, AiChatUsage::query()->count());
    }
}
