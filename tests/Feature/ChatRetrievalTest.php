<?php

declare(strict_types=1);

use Filagora\Ai\Contracts\EmbeddingProvider;
use Filagora\Ai\Retrieval\RetrievalQuery;
use Filagora\Ai\Retrieval\RetrievalSources;
use Filagora\Ai\Services\ChatRetrieval;
use Filagora\Ai\Tests\Support\FakeEmbeddingProvider;
use Filagora\Ai\Tests\Support\StaticSource;
use Filagora\Ai\Tests\Support\TestUser;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    config(['filagora-ai.chat.enabled' => true]);
    $this->embeddings = new FakeEmbeddingProvider;
    app()->instance(EmbeddingProvider::class, $this->embeddings);
    $this->sources = new RetrievalSources;
    app()->instance(RetrievalSources::class, $this->sources);
});

it('puts sources in priority order, not registration order', function () {
    $this->sources
        ->register(new StaticSource('late', [StaticSource::item('threads')]), priority: 300)
        ->register(new StaticSource('early', [StaticSource::item('code')]), priority: 10)
        ->register(new StaticSource('middle', [StaticSource::item('docs')]), priority: 100);

    $titles = array_column(app(ChatRetrieval::class)->retrieve('how?', null), 'title');

    expect($titles)->toBe(['code', 'docs', 'threads']);
});

it('finds nothing while the chat is switched off', function () {
    config(['filagora-ai.chat.enabled' => false]);
    $this->sources->register($source = new StaticSource('docs', [StaticSource::item('docs')]));

    expect(app(ChatRetrieval::class)->retrieve('how?', null))->toBe([])
        ->and($source->lastQuery)->toBeNull();
});

it('replies without grounding when the message cannot be embedded, and says so in the log', function () {
    $this->embeddings->fails = true;
    $this->sources->register($source = new StaticSource('docs', [StaticSource::item('docs')]));
    Log::spy();

    expect(app(ChatRetrieval::class)->retrieve('how?', null))->toBe([])
        ->and($source->lastQuery)->toBeNull();

    Log::shouldHaveReceived('warning')->once();
});

it('skips a source that fails and keeps the others', function () {
    $this->sources
        ->register(new StaticSource('broken', fails: true), priority: 1)
        ->register(new StaticSource('docs', [StaticSource::item('docs')]), priority: 2);
    Log::spy();

    expect(array_column(app(ChatRetrieval::class)->retrieve('how?', null), 'title'))->toBe(['docs']);

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $context['source'] === 'broken')->once();
});

it('hands every source the embedded message, the asker, the hint and the options', function () {
    $this->sources->register($source = new StaticSource('docs'));
    $user = TestUser::make();

    app(ChatRetrieval::class)->retrieve('how?', $user, 'earlier', ['limits' => ['docs' => 2]]);

    expect($source->lastQuery)->toBeInstanceOf(RetrievalQuery::class)
        ->and($source->lastQuery->message)->toBe('how?')
        ->and($source->lastQuery->vector)->toBe([0.1, 0.2])
        ->and($source->lastQuery->user?->getAuthIdentifier())->toBe($user->getAuthIdentifier())
        ->and($source->lastQuery->versionHint)->toBe('earlier')
        ->and($source->lastQuery->limitFor('docs', 8))->toBe(2)
        ->and($source->lastQuery->limitFor('threads', 8))->toBe(8);
});

it('replaces a source registered twice under one key', function () {
    $this->sources
        ->register(new StaticSource('docs', [StaticSource::item('old')]))
        ->register(new StaticSource('docs', [StaticSource::item('new')]));

    expect(array_column(app(ChatRetrieval::class)->retrieve('how?', null), 'title'))->toBe(['new']);
});

it('defaults a source to the configured top-k, never below one', function () {
    config(['filagora-ai.chat.retrieval_top_k' => 5]);
    expect(ChatRetrieval::defaultLimit())->toBe(5);

    config(['filagora-ai.chat.retrieval_top_k' => 0]);
    expect(ChatRetrieval::defaultLimit())->toBe(1);
});
