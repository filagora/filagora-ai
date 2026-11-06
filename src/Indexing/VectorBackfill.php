<?php

declare(strict_types=1);

namespace Filagora\Ai\Indexing;

use Closure;
use Filagora\Ai\Contracts\EmbeddingProvider;
use Filagora\Ai\Contracts\ResetsAfterFailedRun;
use Filagora\Ai\Models\AiEmbeddingUsage;
use Filagora\Ai\Services\EmbeddingBatchFailed;
use Filagora\Ai\Support\Embeddings;
use Filagora\Search\Contracts\SearchIndex;
use Filagora\Search\Contracts\WaitsForTasks;
use Filagora\Search\Enums\Capability;
use Filagora\Search\Exceptions\MeilisearchTaskFailed;
use Filagora\Search\Indexing\Indexer;
use Filagora\Search\Support\SearchEngines;
use Throwable;

/**
 * Fills a vector index from its rows, carefully, because every batch costs an embedding call.
 *
 * Before spending anything it checks that the engine can hold vectors, that the index exists, and
 * that the provider answers with vectors of the length the index declares. While running it makes
 * the engine catch up after the first batch (the one that shows a systemic mistake) and every so
 * many documents after that, and stops at the first batch the engine rejects. A batch whose
 * embedding fails is not sent at all, so no document is left without a vector or loses the one it
 * had. It ends by saying whether the engine was confirmed to have taken everything.
 *
 * The index does the rest: its `BatchesDocuments::toDocuments()` makes the vectors (and throws
 * `EmbeddingBatchFailed` rather than send documents without them), and if it marks rows as indexed
 * it implements `ResetsAfterFailedRun`.
 */
class VectorBackfill
{
    public function __construct(
        private readonly SearchEngines $engines,
        private readonly Indexer $indexer,
        private readonly EmbeddingProvider $embeddings,
    ) {}

    /**
     * Why this index cannot be filled right now, or null if it can.
     */
    public function problemWith(SearchIndex $index, string $usageContext): ?string
    {
        $engine = $this->engines->engine();
        $definition = $index->definition();

        if (! in_array(Capability::VECTORS, $engine->capabilities(), true)) {
            return "The search engine ({$engine->name()}) cannot store vectors; there is no index to fill.";
        }

        if (! Embeddings::enabled() || $definition->vectorDimensions === null) {
            return 'Embeddings are switched off. Without them the index has no embedder and could not store the vectors.';
        }

        if ($engine->stats($definition) === null) {
            return "The index {$definition->name} is missing or the search engine is not answering. Run: php artisan filagora:search:sync-settings --index={$definition->name}";
        }

        try {
            $vectors = $this->embeddings->embedBatch(['vector backfill preflight'], "{$usageContext}_preflight");
        } catch (Throwable $e) {
            return 'The embedding provider is not answering, so nothing was sent: '.$e->getMessage();
        }

        $length = count($vectors[0] ?? []);

        if ($length !== $definition->vectorDimensions) {
            return "The embedding provider returns {$length}-dimension vectors but the index expects {$definition->vectorDimensions}. Nothing was sent.";
        }

        return null;
    }

    /**
     * @param  string  $usageContext  the context the index's embedding calls are recorded under, to total their cost
     * @param  int  $catchUpEvery  after this many documents, wait for the engine to catch up before sending more
     * @param  Closure(int, int|string|null): void|null  $progress  told the documents sent and the last key looked at, after each batch
     */
    public function run(
        SearchIndex $index,
        string $usageContext,
        int|string|null $fromKey = null,
        int $batchSize = 100,
        ?int $limit = null,
        int $sleepMs = 250,
        int $catchUpEvery = 2000,
        ?Closure $progress = null,
    ): BackfillResult {
        if (($problem = $this->problemWith($index, $usageContext)) !== null) {
            return new BackfillResult(BackfillStop::NOT_READY, message: $problem);
        }

        $engine = $this->engines->engine();
        $startedAt = now();
        $started = microtime(true);
        $batches = 0;
        $caughtUpAt = 0;
        $sent = 0;
        $lastKey = null;

        $watch = function (int $total, int|string|null $key) use ($engine, $progress, $catchUpEvery, &$batches, &$caughtUpAt, &$sent, &$lastKey): void {
            $sent = $total;
            $lastKey = $key;
            $batches++;

            if ($engine instanceof WaitsForTasks && ($batches === 1 || $total - $caughtUpAt >= $catchUpEvery)) {
                $caughtUpAt = $total;
                $engine->waitForTasks(900);
            }

            if ($progress !== null) {
                $progress($total, $key);
            }
        };

        $stop = BackfillStop::COMPLETED;
        $failure = null;

        try {
            $this->indexer->reindex($index, $fromKey, max(1, $batchSize), $limit, $sleepMs, $watch);
        } catch (EmbeddingBatchFailed $e) {
            [$stop, $failure] = [BackfillStop::EMBEDDING_FAILED, $e];
        } catch (MeilisearchTaskFailed $e) {
            [$stop, $failure] = [str_contains($e->getMessage(), 'did not finish') ? BackfillStop::STALLED : BackfillStop::REJECTED, $e];
        } catch (Throwable $e) {
            report($e);
            [$stop, $failure] = [BackfillStop::CRASHED, $e];
        }

        // A failed embedding or a crash is different from a rejection: the earlier batches were each
        // checked as they went. After a rejection or a stall the rows marked as sent may not have been taken.
        if (($stop === BackfillStop::REJECTED || $stop === BackfillStop::STALLED) && $index instanceof ResetsAfterFailedRun) {
            $index->resetAfterFailedRun($startedAt);
        }

        $usage = AiEmbeddingUsage::query()
            ->where('context', $usageContext)
            ->where('created_at', '>=', $startedAt)
            ->selectRaw('coalesce(sum(input_tokens), 0) as tokens, coalesce(sum(estimated_cost_usd), 0) as cost')
            ->toBase()
            ->first();

        return new BackfillResult(
            $stop,
            $sent,
            $lastKey,
            $failure?->getMessage(),
            (int) ($usage->tokens ?? 0),
            (float) ($usage->cost ?? 0),
            (int) round(microtime(true) - $started),
            $failure,
        );
    }
}
