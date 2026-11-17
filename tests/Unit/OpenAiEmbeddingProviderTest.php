<?php

namespace Filagora\Ai\Tests\Unit;

use Filagora\Ai\Models\AiEmbeddingUsage;
use Filagora\Ai\Providers\OpenAiEmbeddingProvider;
use Filagora\Ai\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class OpenAiEmbeddingProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'filagora-ai.embeddings.openai.api_key' => 'test-key',
            'filagora-ai.embeddings.openai.embedding_model' => 'text-embedding-3-small',
            'filagora-ai.embeddings.openai.dimensions' => 3,
        ]);
    }

    public function test_embed_sends_the_configured_model_and_returns_the_vector(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [
                    ['index' => 0, 'embedding' => [0.1, 0.2, 0.3]],
                ],
                'usage' => ['total_tokens' => 4],
            ]),
        ]);
        config(['filagora-ai.embeddings.cost_per_million_tokens.openai.text-embedding-3-small' => 0.02]);

        $vector = (new OpenAiEmbeddingProvider)->embed('checkout is broken', 'thread_index');

        $this->assertSame([0.1, 0.2, 0.3], $vector);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.openai.com/v1/embeddings'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && $request['model'] === 'text-embedding-3-small'
                && $request['input'] === ['checkout is broken'];
        });
        $this->assertDatabaseHas(AiEmbeddingUsage::class, [
            'provider' => 'openai',
            'model' => 'text-embedding-3-small',
            'context' => 'thread_index',
            'input_tokens' => 4,
            'succeeded' => true,
        ]);
    }

    public function test_embed_records_a_failed_usage_row_when_the_request_throws(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response(['error' => 'bad request'], 400),
        ]);

        try {
            (new OpenAiEmbeddingProvider)->embed('anything', 'search_query');
        } catch (RequestException) {
            // asserted separately below; this test only cares about the usage row.
        }

        $this->assertDatabaseHas(AiEmbeddingUsage::class, [
            'provider' => 'openai',
            'context' => 'search_query',
            'input_tokens' => null,
            'succeeded' => false,
        ]);
    }

    public function test_embed_batch_returns_vectors_ordered_by_index_regardless_of_response_order(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [
                    ['index' => 1, 'embedding' => [0.4, 0.5]],
                    ['index' => 0, 'embedding' => [0.1, 0.2]],
                ],
            ]),
        ]);

        $vectors = (new OpenAiEmbeddingProvider)->embedBatch(['first', 'second']);

        $this->assertSame([[0.1, 0.2], [0.4, 0.5]], $vectors);
    }

    public function test_embed_throws_on_a_failed_response(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response(['error' => 'bad request'], 400),
        ]);

        $this->expectException(RequestException::class);

        (new OpenAiEmbeddingProvider)->embed('anything');
    }

    public function test_a_large_batch_is_split_into_requests_of_at_most_max_batch_and_keeps_its_order(): void
    {
        config(['filagora-ai.embeddings.max_batch' => 2]);
        Http::fake(fn ($request) => Http::response([
            'data' => collect($request['input'])
                ->map(fn (string $text, int $index): array => ['index' => $index, 'embedding' => [strlen($text) + 0.5]])
                ->all(),
            'usage' => ['total_tokens' => count($request['input'])],
        ]));

        $vectors = (new OpenAiEmbeddingProvider)->embedBatch(['a', 'bb', 'ccc', 'dddd', 'eeeee'], 'plugin_release_index');

        $this->assertSame([[1.5], [2.5], [3.5], [4.5], [5.5]], $vectors);
        $sizes = [];
        Http::assertSent(function ($request) use (&$sizes): bool {
            $sizes[] = count($request['input']);

            return true;
        });
        $this->assertSame([2, 2, 1], $sizes);
        $this->assertSame(3, AiEmbeddingUsage::query()->where('context', 'plugin_release_index')->where('succeeded', true)->count());
        $this->assertSame(5, (int) AiEmbeddingUsage::query()->where('context', 'plugin_release_index')->sum('input_tokens'));
    }

    public function test_a_batch_within_max_batch_is_one_request(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['data' => [['index' => 0, 'embedding' => [0.1]], ['index' => 1, 'embedding' => [0.2]]]])]);

        (new OpenAiEmbeddingProvider)->embedBatch(['one', 'two']);

        Http::assertSentCount(1);
    }

    public function test_a_failed_chunk_fails_the_call_and_is_recorded_after_the_chunks_that_succeeded(): void
    {
        config(['filagora-ai.embeddings.max_batch' => 1]);
        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push(['data' => [['index' => 0, 'embedding' => [0.1]]], 'usage' => ['total_tokens' => 1]])
                ->push(['error' => 'rate limited'], 429),
        ]);

        try {
            (new OpenAiEmbeddingProvider)->embedBatch(['first', 'second'], 'doc_page_index');
            $this->fail('The second chunk should have failed the call.');
        } catch (RequestException) {
            // expected
        }

        $this->assertSame(1, AiEmbeddingUsage::query()->where('context', 'doc_page_index')->where('succeeded', true)->count());
        $this->assertSame(1, AiEmbeddingUsage::query()->where('context', 'doc_page_index')->where('succeeded', false)->count());
    }

    public function test_an_empty_batch_sends_nothing_and_returns_no_vectors(): void
    {
        Http::fake();

        $this->assertSame([], (new OpenAiEmbeddingProvider)->embedBatch([]));

        Http::assertNothingSent();
    }

    public function test_dimensions_reads_the_configured_value(): void
    {
        $this->assertSame(3, (new OpenAiEmbeddingProvider)->dimensions());
    }
}
