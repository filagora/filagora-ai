<?php

declare(strict_types=1);

namespace Filagora\Ai\Tests\Support;

use Filagora\Ai\Contracts\EmbeddingProvider;
use RuntimeException;

class FakeEmbeddingProvider implements EmbeddingProvider
{
    public bool $fails = false;

    public function embed(string $text, ?string $context = null): array
    {
        if ($this->fails) {
            throw new RuntimeException('embeddings are down');
        }

        return [0.1, 0.2];
    }

    public function embedBatch(array $texts, ?string $context = null): array
    {
        return array_map(fn (string $text): array => $this->embed($text), $texts);
    }

    public function dimensions(): int
    {
        return 2;
    }
}
