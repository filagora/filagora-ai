<?php

namespace Filagora\Ai\Tests\Unit;

use Filagora\Ai\Contracts\EmbeddingProvider;
use Filagora\Ai\Providers\OpenAiEmbeddingProvider;
use Filagora\Ai\Providers\VoyageEmbeddingProvider;
use Filagora\Ai\Services\EmbeddingProviderManager;
use Filagora\Ai\Tests\TestCase;
use InvalidArgumentException;

class EmbeddingProviderManagerTest extends TestCase
{
    public function test_resolves_the_openai_provider_by_default(): void
    {
        config(['filagora-ai.embeddings.driver' => 'openai']);

        $this->assertInstanceOf(OpenAiEmbeddingProvider::class, (new EmbeddingProviderManager)->resolve());
    }

    public function test_resolves_the_voyage_provider(): void
    {
        config(['filagora-ai.embeddings.driver' => 'voyage']);

        $this->assertInstanceOf(VoyageEmbeddingProvider::class, (new EmbeddingProviderManager)->resolve());
    }

    public function test_a_host_can_add_a_driver_and_it_wins_over_a_built_in(): void
    {
        $custom = new class implements EmbeddingProvider
        {
            public function embed(string $text, ?string $context = null): array
            {
                return [0.0];
            }

            public function embedBatch(array $texts, ?string $context = null): array
            {
                return [[0.0]];
            }

            public function dimensions(): int
            {
                return 1;
            }
        };
        $manager = (new EmbeddingProviderManager)->extend('custom', fn () => $custom)->extend('openai', fn () => $custom);

        config(['filagora-ai.embeddings.driver' => 'custom']);
        $this->assertSame($custom, $manager->resolve());

        config(['filagora-ai.embeddings.driver' => 'openai']);
        $this->assertSame($custom, $manager->resolve());
    }

    public function test_an_unknown_driver_throws(): void
    {
        config(['filagora-ai.embeddings.driver' => 'not-a-real-driver']);

        $this->expectException(InvalidArgumentException::class);

        (new EmbeddingProviderManager)->resolve();
    }
}
