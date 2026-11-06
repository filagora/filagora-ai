<?php

declare(strict_types=1);

namespace Filagora\Ai\Contracts;

use Filagora\Ai\Retrieval\RetrievalQuery;

/**
 * Somewhere the chat can look for what grounds an answer: forum threads, documentation, plugins,
 * source code. A host or another package registers one with `RetrievalSources::register()`.
 *
 * A source returns only what the person asking may see. It is asked with the message already
 * embedded; a source that is switched off, or whose index is not built yet, returns an empty list.
 * One that throws is skipped and logged, so it never takes the others down.
 */
interface RetrievalSource
{
    /**
     * Names the source in `RetrievalQuery::limitFor()` and in logs.
     */
    public function key(): string;

    /**
     * @return list<array{type: string, id: int|string, title: string, url: string, excerpt: string, label?: string, note?: string}>
     *                                                                                                                               `label` prefixes the excerpt in the prompt (such as CODE); `note` is a sentence added once to the prompt
     *                                                                                                                               when any item carries it. A source may add keys of its own.
     */
    public function retrieve(RetrievalQuery $query): array;
}
