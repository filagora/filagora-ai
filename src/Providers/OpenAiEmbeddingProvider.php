<?php

declare(strict_types=1);

namespace Filagora\Ai\Providers;

class OpenAiEmbeddingProvider extends AbstractHttpEmbeddingProvider
{
    protected function providerName(): string
    {
        return 'openai';
    }

    protected function endpoint(): string
    {
        return 'https://api.openai.com/v1/embeddings';
    }

    protected function apiKey(): string
    {
        return (string) config('filagora-ai.embeddings.openai.api_key');
    }

    protected function model(): string
    {
        return (string) config('filagora-ai.embeddings.openai.embedding_model');
    }

    public function dimensions(): int
    {
        return (int) config('filagora-ai.embeddings.openai.dimensions');
    }
}
