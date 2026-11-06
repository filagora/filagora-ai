<?php

declare(strict_types=1);

namespace Filagora\Ai\Support;

use Filagora\Ai\Contracts\EmbeddingProvider;

/**
 * Whether embeddings are switched on, and how long the vectors are. A vector index declares its
 * length from this, so an index only has vectors while embeddings are on: with them off no document
 * carries one and the search engine is never told about an embedder.
 */
final class Embeddings
{
    public static function enabled(): bool
    {
        return (bool) config('filagora-ai.embeddings.enabled');
    }

    /**
     * The length of the vectors the configured provider makes, or null while embeddings are off.
     */
    public static function dimensions(): ?int
    {
        return self::enabled() ? app(EmbeddingProvider::class)->dimensions() : null;
    }
}
