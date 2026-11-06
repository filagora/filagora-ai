<?php

declare(strict_types=1);

namespace Filagora\Ai\Providers;

class VoyageEmbeddingProvider extends AbstractHttpEmbeddingProvider
{
    protected function providerName(): string
    {
        return 'voyage';
    }

    protected function endpoint(): string
    {
        return 'https://api.voyageai.com/v1/embeddings';
    }

    protected function apiKey(): string
    {
        return (string) config('filagora-ai.embeddings.voyage.api_key');
    }

    protected function model(): string
    {
        return (string) config('filagora-ai.embeddings.voyage.embedding_model');
    }

    public function dimensions(): int
    {
        return (int) config('filagora-ai.embeddings.voyage.dimensions');
    }
}
