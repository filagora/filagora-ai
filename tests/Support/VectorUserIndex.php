<?php

declare(strict_types=1);

namespace Filagora\Ai\Tests\Support;

use Carbon\CarbonInterface;
use Filagora\Ai\Contracts\EmbeddingProvider;
use Filagora\Ai\Contracts\ResetsAfterFailedRun;
use Filagora\Ai\Services\EmbeddingBatchFailed;
use Filagora\Ai\Support\Embeddings;
use Filagora\Search\Contracts\BatchesDocuments;
use Filagora\Search\Contracts\SearchIndex;
use Filagora\Search\Support\Document;
use Filagora\Search\Support\IndexDefinition;
use Filagora\Search\Support\SearchQuery;
use Filagora\Search\Support\SearchResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Users with a vector each, embedded a chunk at a time under the context `user_index`; refuses to
 * send a chunk whose embedding failed, and remembers being told to take its marks back.
 */
class VectorUserIndex implements BatchesDocuments, ResetsAfterFailedRun, SearchIndex
{
    public ?CarbonInterface $resetSince = null;

    public function definition(): IndexDefinition
    {
        return new IndexDefinition('users', ['name'], vectorDimensions: Embeddings::dimensions());
    }

    public function model(): string
    {
        return TestUser::class;
    }

    public function shouldBeSearchable(Model $model): bool
    {
        return true;
    }

    public function toDocument(Model $model): Document
    {
        return $this->toDocuments(new Collection([$model]))[0];
    }

    public function toDocuments(Collection $models): array
    {
        try {
            $vectors = app(EmbeddingProvider::class)->embedBatch($models->map(fn ($model): string => (string) $model->getAttribute('name'))->all(), 'user_index');
        } catch (Throwable $e) {
            throw new EmbeddingBatchFailed($e->getMessage(), $e);
        }

        return $models->values()->map(fn ($model, int $i): Document => new Document($model->getKey(), ['name' => $model->getAttribute('name')], $vectors[$i]))->all();
    }

    public function query(): Builder
    {
        return TestUser::query();
    }

    public function expectedCount(): int
    {
        return TestUser::query()->count();
    }

    public function fallback(SearchQuery $query): SearchResult
    {
        return new SearchResult([], 0);
    }

    public function resetAfterFailedRun(CarbonInterface $startedAt): void
    {
        $this->resetSince = $startedAt;
    }
}
