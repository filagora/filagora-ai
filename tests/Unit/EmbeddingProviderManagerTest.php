<?php

namespace Filagora\Ai\Tests\Unit;

use Filagora\Ai\Providers\DigitalOceanEmbeddingProvider;
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

    public function test_resolves_the_digitalocean_provider(): void
    {
        config(['filagora-ai.embeddings.driver' => 'digitalocean']);

        $this->assertInstanceOf(DigitalOceanEmbeddingProvider::class, (new EmbeddingProviderManager)->resolve());
    }

    public function test_an_unknown_driver_throws(): void
    {
        config(['filagora-ai.embeddings.driver' => 'not-a-real-driver']);

        $this->expectException(InvalidArgumentException::class);

        (new EmbeddingProviderManager)->resolve();
    }
}
