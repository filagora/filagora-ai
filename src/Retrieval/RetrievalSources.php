<?php

declare(strict_types=1);

namespace Filagora\Ai\Retrieval;

use Filagora\Ai\Contracts\RetrievalSource;

/**
 * The sources the chat searches, in the order their results are put to the model: the lower the
 * priority number, the earlier. A source registered under a key that already exists replaces it.
 */
class RetrievalSources
{
    /** @var array<string, array{source: RetrievalSource, priority: int}> */
    private array $sources = [];

    public function register(RetrievalSource $source, int $priority = 100): static
    {
        $this->sources[$source->key()] = ['source' => $source, 'priority' => $priority];

        return $this;
    }

    /**
     * @return list<RetrievalSource>
     */
    public function all(): array
    {
        $entries = array_values($this->sources);

        usort($entries, fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

        return array_map(fn (array $entry): RetrievalSource => $entry['source'], $entries);
    }
}
