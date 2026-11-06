<?php

declare(strict_types=1);

namespace Filagora\Ai\Services;

use Filagora\Ai\Contracts\EmbeddingProvider;
use Filagora\Ai\Retrieval\RetrievalQuery;
use Filagora\Ai\Retrieval\RetrievalSources;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The retrieval half of the chat: embeds the message once and asks every registered source what
 * it has, each on its own so one missing or unreachable index does not take the rest down.
 * Off (`filagora-ai.chat.enabled`), or unable to embed, it finds nothing and the chat answers
 * without grounding.
 */
class ChatRetrieval
{
    public function __construct(
        private readonly RetrievalSources $sources,
        private readonly EmbeddingProvider $embeddings,
    ) {}

    /**
     * How many items a source takes unless the caller says otherwise.
     */
    public static function defaultLimit(): int
    {
        return max(1, (int) config('filagora-ai.chat.retrieval_top_k', 8));
    }

    /**
     * @param  string|null  $versionHint  earlier text from the same conversation
     * @param  array<string, mixed>  $options  see RetrievalQuery
     * @return list<array<string, mixed>> each with type, id, title, url and excerpt
     */
    public function retrieve(string $message, ?Authenticatable $user, ?string $versionHint = null, array $options = []): array
    {
        if (! (bool) config('filagora-ai.chat.enabled')) {
            return [];
        }

        try {
            $vector = $this->embeddings->embed($message, 'ai_chat_query');
        } catch (Throwable $e) {
            Log::warning('Failed to embed an AI chat message for retrieval; replying without grounding.', [
                'exception' => $e->getMessage(),
            ]);

            return [];
        }

        $query = new RetrievalQuery($message, $vector, $user, $versionHint, $options);
        $items = [];

        foreach ($this->sources->all() as $source) {
            try {
                array_push($items, ...$source->retrieve($query));
            } catch (Throwable $e) {
                Log::warning('Failed to search an AI chat retrieval source; replying without it.', [
                    'source' => $source->key(),
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return $items;
    }
}
