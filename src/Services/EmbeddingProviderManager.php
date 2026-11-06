<?php

declare(strict_types=1);

namespace Filagora\Ai\Services;

use Closure;
use Filagora\Ai\Contracts\EmbeddingProvider;
use Filagora\Ai\Providers\OpenAiEmbeddingProvider;
use Filagora\Ai\Providers\VoyageEmbeddingProvider;
use InvalidArgumentException;

/**
 * Picks the embedding provider named by `filagora-ai.embeddings.driver`. A host adds its own
 * with `extend()`, which takes precedence over a built-in of the same name.
 */
class EmbeddingProviderManager
{
    /** @var array<string, Closure(): EmbeddingProvider> */
    private array $extensions = [];

    /**
     * @param  Closure(): EmbeddingProvider  $factory
     */
    public function extend(string $driver, Closure $factory): static
    {
        $this->extensions[$driver] = $factory;

        return $this;
    }

    public function resolve(): EmbeddingProvider
    {
        $driver = (string) config('filagora-ai.embeddings.driver', 'openai');

        if (isset($this->extensions[$driver])) {
            return ($this->extensions[$driver])();
        }

        return match ($driver) {
            'openai' => new OpenAiEmbeddingProvider,
            'voyage' => new VoyageEmbeddingProvider,
            default => throw new InvalidArgumentException("Unknown AI embeddings driver [{$driver}]."),
        };
    }
}
