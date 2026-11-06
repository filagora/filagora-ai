<?php

namespace Filagora\Ai\Tests\Unit;

use Filagora\Ai\Models\AiEmbeddingUsage;
use Filagora\Ai\Services\EmbeddingUsageRecorder;
use Filagora\Ai\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class EmbeddingUsageRecorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_success_estimates_cost_from_the_configured_rate(): void
    {
        config(['filagora-ai.embeddings.cost_per_million_tokens.openai.text-embedding-3-small' => 0.02]);

        (new EmbeddingUsageRecorder)->recordSuccess('openai', 'text-embedding-3-small', 500_000, 'thread_index');

        $this->assertDatabaseHas(AiEmbeddingUsage::class, [
            'provider' => 'openai',
            'model' => 'text-embedding-3-small',
            'context' => 'thread_index',
            'input_tokens' => 500_000,
            'estimated_cost_usd' => '0.010000',
            'succeeded' => true,
        ]);
    }

    public function test_record_success_leaves_cost_null_when_no_rate_is_configured(): void
    {
        config(['filagora-ai.embeddings.cost_per_million_tokens.openai.text-embedding-3-small' => null]);

        (new EmbeddingUsageRecorder)->recordSuccess('openai', 'text-embedding-3-small', 1000, 'search_query');

        $this->assertDatabaseHas(AiEmbeddingUsage::class, [
            'provider' => 'openai',
            'context' => 'search_query',
            'input_tokens' => 1000,
            'estimated_cost_usd' => null,
        ]);
    }

    public function test_record_failure_stores_null_tokens_and_cost(): void
    {
        (new EmbeddingUsageRecorder)->recordFailure('voyage', 'voyage-3-lite', 'plugin_release_index');

        $this->assertDatabaseHas(AiEmbeddingUsage::class, [
            'provider' => 'voyage',
            'model' => 'voyage-3-lite',
            'context' => 'plugin_release_index',
            'input_tokens' => null,
            'estimated_cost_usd' => null,
            'succeeded' => false,
        ]);
    }

    public function test_a_database_failure_while_recording_is_logged_not_thrown(): void
    {
        Log::spy();
        AiEmbeddingUsage::creating(fn () => throw new \RuntimeException('database is down'));

        (new EmbeddingUsageRecorder)->recordSuccess('openai', 'text-embedding-3-small', 10, 'thread_index');

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message): bool => $message === 'Failed to record AI embedding usage.');

        AiEmbeddingUsage::flushEventListeners();
    }
}
