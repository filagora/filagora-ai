<?php

declare(strict_types=1);

use Filagora\Ai\Contracts\EmbeddingProvider;
use Filagora\Ai\Indexing\BackfillStop;
use Filagora\Ai\Indexing\VectorBackfill;
use Filagora\Ai\Models\AiEmbeddingUsage;
use Filagora\Ai\Tests\Support\FakeEmbeddingProvider;
use Filagora\Ai\Tests\Support\ScriptedEngine;
use Filagora\Ai\Tests\Support\TestUser;
use Filagora\Ai\Tests\Support\VectorUserIndex;
use Filagora\Search\Exceptions\MeilisearchTaskFailed;
use Filagora\Search\Support\SearchEngines;
use Filagora\Search\Support\SearchQuery;

beforeEach(function () {
    config(['filagora-ai.embeddings.enabled' => true, 'filagora-search.engine' => 'scripted']);
    $this->embeddings = new FakeEmbeddingProvider;
    app()->instance(EmbeddingProvider::class, $this->embeddings);
    $this->engine = new ScriptedEngine;
    app(SearchEngines::class)->extend('scripted', fn () => $this->engine);
    $this->index = new VectorUserIndex;
    $this->engine->syncSettings($this->index->definition());
    $this->users = collect(range(1, 5))->map(fn (int $n) => TestUser::make("User{$n}"));

    $this->backfill = fn (array $options = []) => app(VectorBackfill::class)->run($this->index, 'user_index', ...[...['sleepMs' => 0, 'batchSize' => 2], ...$options]);
});

it('sends every row with its vector, makes the engine catch up after the first batch, and says it is done', function () {
    $result = ($this->backfill)();

    expect($result->succeeded())->toBeTrue()
        ->and($result->sent)->toBe(5)
        ->and($result->lastKey)->toBe($this->users[4]->id)
        ->and($this->engine->waits)->toBe(2)
        ->and($this->engine->search($this->index->definition(), new SearchQuery(vector: [0.1, 0.2], perPage: 10))->total)->toBe(5);
});

it('catches up again every so many documents', function () {
    $result = ($this->backfill)(['batchSize' => 1, 'catchUpEvery' => 2]);

    expect($result->succeeded())->toBeTrue()
        ->and($this->engine->waits)->toBe(4);
});

it('tells the caller how far it has got after each batch', function () {
    $seen = [];

    ($this->backfill)(['progress' => function (int $sent, int|string|null $key) use (&$seen) {
        $seen[] = [$sent, $key];
    }]);

    expect($seen)->toBe([[2, $this->users[1]->id], [4, $this->users[3]->id], [5, $this->users[4]->id]]);
});

it('resumes at a key and stops at a limit', function () {
    $result = ($this->backfill)(['fromKey' => $this->users[1]->id, 'limit' => 2]);

    expect($result->sent)->toBe(2)
        ->and($result->lastKey)->toBe($this->users[2]->id);
});

it('refuses to start, spending nothing, when the engine cannot hold vectors', function () {
    $engine = new class extends ScriptedEngine
    {
        public function capabilities(): array
        {
            return [];
        }
    };
    app(SearchEngines::class)->extend('scripted', fn () => $engine);

    $result = ($this->backfill)();

    expect($result->stop)->toBe(BackfillStop::NOT_READY)
        ->and($result->message)->toContain('cannot store vectors');
});

it('refuses to start while embeddings are off', function () {
    config(['filagora-ai.embeddings.enabled' => false]);

    expect(($this->backfill)()->message)->toContain('Embeddings are switched off');
});

it('refuses to start when the index has not been created', function () {
    $this->engine = new ScriptedEngine;
    app(SearchEngines::class)->extend('scripted', fn () => $this->engine);

    expect(($this->backfill)()->message)->toContain('filagora:search:sync-settings');
});

it('refuses to start when the provider is down, before any row is embedded', function () {
    $this->embeddings->fails = true;

    $result = ($this->backfill)();

    expect($result->stop)->toBe(BackfillStop::NOT_READY)
        ->and($result->message)->toContain('not answering');
});

it('refuses to start when the provider\'s vectors are the wrong length', function () {
    $this->embeddings->dimensionsReturned = 3;

    expect(($this->backfill)()->message)->toContain('3-dimension vectors but the index expects 2');
});

it('stops without sending a batch whose embedding failed, and says where to resume', function () {
    $this->embeddings->failAfterBatches = 1;

    $result = ($this->backfill)();

    expect($result->stop)->toBe(BackfillStop::EMBEDDING_FAILED)
        ->and($result->sent)->toBe(2)
        ->and($result->lastKey)->toBe($this->users[1]->id)
        ->and($this->index->resetSince)->toBeNull()
        ->and($this->engine->search($this->index->definition(), new SearchQuery(vector: [0.1, 0.2], perPage: 10))->total)->toBe(2);
});

it('stops at the first rejected batch and takes the index\'s marks back', function () {
    $this->engine->failOnWait = 1;
    $this->engine->failure = new MeilisearchTaskFailed('Meilisearch task 7 failed: invalid vector');

    $result = ($this->backfill)();

    expect($result->stop)->toBe(BackfillStop::REJECTED)
        ->and($result->message)->toContain('invalid vector')
        ->and($this->index->resetSince)->not->toBeNull()
        ->and($this->embeddings->batchesEmbedded)->toBe(1);
});

it('tells a stalled engine from a rejecting one', function () {
    $this->engine->failOnWait = 2;
    $this->engine->failure = new MeilisearchTaskFailed('Meilisearch task 9 did not finish within 900 seconds.');

    $result = ($this->backfill)(['batchSize' => 5]);

    expect($result->stop)->toBe(BackfillStop::STALLED)
        ->and($this->index->resetSince)->not->toBeNull();
});

it('totals the tokens and cost of the run from the usage records', function () {
    config(['filagora-ai.embeddings.cost_per_million_tokens.fake.fake-1' => 1.0]);
    $this->embeddings->recordUsage = fn (string $context) => AiEmbeddingUsage::query()->create([
        'provider' => 'fake', 'model' => 'fake-1', 'context' => $context, 'input_tokens' => 1000, 'estimated_cost_usd' => 0.5, 'succeeded' => true,
    ]);

    $result = ($this->backfill)();

    expect($result->tokens)->toBe(3000)
        ->and($result->costUsd)->toBe(1.5);
});
