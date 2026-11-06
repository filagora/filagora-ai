<?php

declare(strict_types=1);

namespace Filagora\Ai\Services;

use Filagora\Ai\Contracts\EmbeddingProvider;
use Filagora\Ai\Providers\DigitalOceanEmbeddingProvider;
use Filagora\Ai\Providers\OpenAiEmbeddingProvider;
use Filagora\Ai\Providers\VoyageEmbeddingProvider;
use InvalidArgumentException;

class EmbeddingProviderManager
{
    public function resolve(): EmbeddingProvider
    {
        $driver = (string) config('filagora-ai.embeddings.driver', 'openai');

        return match ($driver) {
            'openai' => new OpenAiEmbeddingProvider,
            'voyage' => new VoyageEmbeddingProvider,
            'digitalocean' => new DigitalOceanEmbeddingProvider,
            default => throw new InvalidArgumentException("Unknown AI embeddings driver [{$driver}]."),
        };
    }
}
