<?php

namespace Filagora\Ai\Tests\Unit;

use Filagora\Ai\Providers\DigitalOceanEmbeddingProvider;
use Filagora\Ai\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

/**
 * DigitalOceanEmbeddingProvider shares its HTTP/parsing logic with
 * OpenAiEmbeddingProvider via AbstractHttpEmbeddingProvider (see
 * OpenAiEmbeddingProviderTest for that shared behavior, including usage
 * recording) - this only covers what's specific to DigitalOcean: its
 * endpoint and config keys. RefreshDatabase is still needed here because
 * embed() records to ai_embedding_usages as a side effect.
 */
class DigitalOceanEmbeddingProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_embed_sends_the_configured_model_to_the_digitalocean_endpoint(): void
    {
        config([
            'filagora-ai.embeddings.digitalocean.api_key' => 'do-key',
            'filagora-ai.embeddings.digitalocean.embedding_model' => 'bge-m3',
            'filagora-ai.embeddings.digitalocean.dimensions' => 4,
        ]);

        Http::fake([
            'inference.do-ai.run/*' => Http::response([
                'data' => [
                    ['index' => 0, 'embedding' => [0.9, 0.8, 0.7, 0.6]],
                ],
            ]),
        ]);

        $vector = (new DigitalOceanEmbeddingProvider)->embed('checkout is broken');

        $this->assertSame([0.9, 0.8, 0.7, 0.6], $vector);
        $this->assertSame(4, (new DigitalOceanEmbeddingProvider)->dimensions());
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://inference.do-ai.run/v1/embeddings'
                && $request->hasHeader('Authorization', 'Bearer do-key')
                && $request['model'] === 'bge-m3';
        });
    }
}
