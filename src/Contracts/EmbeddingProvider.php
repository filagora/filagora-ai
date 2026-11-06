<?php

declare(strict_types=1);

namespace Filagora\Ai\Contracts;

interface EmbeddingProvider
{
    /**
     * @param  ?string  $context  Free-form caller identifier (e.g.
     *                            "thread_index", "search_query") recorded
     *                            alongside usage/cost for this call - see
     *                            EmbeddingUsageRecorder. Purely for analytics;
     *                            has no effect on the embedding itself.
     * @return list<float>
     */
    public function embed(string $text, ?string $context = null): array;

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embedBatch(array $texts, ?string $context = null): array;

    public function dimensions(): int;
}
