<?php

namespace Filagora\Ai\Tests\Unit;

use Filagora\Ai\Providers\VoyageEmbeddingProvider;
use Filagora\Ai\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

/**
 * VoyageEmbeddingProvider shares its HTTP/parsing logic with
 * OpenAiEmbeddingProvider via AbstractHttpEmbeddingProvider (see
 * OpenAiEmbeddingProviderTest for that shared behavior, including usage
 * recording) - this only covers what's specific to Voyage: its endpoint and
 * config keys. RefreshDatabase is still needed here because embed() records
 * to ai_embedding_usages as a side effect.
 */
class VoyageEmbeddingProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_embed_sends_the_configured_model_to_the_voyage_endpoint(): void
    {
        config([
            'filagora-ai.embeddings.voyage.api_key' => 'voyage-key',
            'filagora-ai.embeddings.voyage.embedding_model' => 'voyage-3-lite',
            'filagora-ai.embeddings.voyage.dimensions' => 2,
        ]);

        Http::fake([
            'api.voyageai.com/*' => Http::response([
                'data' => [
                    ['index' => 0, 'embedding' => [0.7, 0.8]],
                ],
            ]),
        ]);

        $vector = (new VoyageEmbeddingProvider)->embed('checkout is broken');

        $this->assertSame([0.7, 0.8], $vector);
        $this->assertSame(2, (new VoyageEmbeddingProvider)->dimensions());
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.voyageai.com/v1/embeddings'
                && $request->hasHeader('Authorization', 'Bearer voyage-key')
                && $request['model'] === 'voyage-3-lite';
        });
    }
}
