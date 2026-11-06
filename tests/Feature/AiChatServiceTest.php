<?php

declare(strict_types=1);

use Filagora\Ai\Contracts\ChatProvider;
use Filagora\Ai\Contracts\EmbeddingProvider;
use Filagora\Ai\Enums\AiChatMessageRole;
use Filagora\Ai\Retrieval\RetrievalSources;
use Filagora\Ai\Services\AiChatService;
use Filagora\Ai\Services\GroundingPrompt;
use Filagora\Ai\Tests\Support\FakeChatProvider;
use Filagora\Ai\Tests\Support\FakeEmbeddingProvider;
use Filagora\Ai\Tests\Support\StaticSource;
use Filagora\Ai\Tests\Support\TestUser;

beforeEach(function () {
    config(['filagora-ai.chat.enabled' => true]);
    $this->provider = new FakeChatProvider;
    app()->instance(ChatProvider::class, $this->provider);
    app()->instance(EmbeddingProvider::class, new FakeEmbeddingProvider);
    $this->sources = new RetrievalSources;
    app()->instance(RetrievalSources::class, $this->sources);
    $this->user = TestUser::make('Asker');
});

function consume(Generator $replies): string
{
    $text = '';
    foreach ($replies as $delta) {
        $text .= $delta;
    }

    return $text;
}

it('starts a conversation titled by the first message, for the provider in use', function () {
    $conversation = app(AiChatService::class)->startConversation($this->user, '  How do I install this?  ');

    expect($conversation->user_id)->toBe($this->user->id)
        ->and($conversation->title)->toBe('How do I install this?')
        ->and($conversation->provider)->toBe('fake')
        ->and($conversation->model)->toBe('fake-1');
});

it('clips a long first message to the title length', function () {
    $conversation = app(AiChatService::class)->startConversation($this->user, str_repeat('a', 300));

    expect(mb_strlen($conversation->title))->toBe(120)
        ->and($conversation->title)->toEndWith('...');
});

it('streams the reply, keeps it with what it was grounded on, and charges the asker', function () {
    $this->sources->register(new StaticSource('docs', [StaticSource::item('Install guide')]));
    $service = app(AiChatService::class);
    $conversation = $service->startConversation($this->user, 'How?');
    $service->recordUserMessage($conversation, 'How?');

    $text = consume($service->streamAssistantReply($conversation, $this->user, 'How?'));

    $reply = $conversation->messages()->latest('id')->first();

    expect($text)->toBe('Hello there.')
        ->and($reply->role)->toBe(AiChatMessageRole::ASSISTANT)
        ->and($reply->content)->toBe('Hello there.')
        ->and($reply->retrieved_context[0]['title'])->toBe('Install guide')
        ->and($this->provider->calls[0]['context'])->toBe('ai_chat_reply')
        ->and($this->provider->calls[0]['userId'])->toBe($this->user->id);
});

it('opens the system prompt from config, then the host rules, then the excerpts', function () {
    config(['filagora-ai.chat.system_prompt' => 'You answer questions about widgets.']);
    app(GroundingPrompt::class)->addRules('Prefer the manual over forum posts.');
    $this->sources->register(new StaticSource('docs', [StaticSource::item('Manual', ['label' => 'MANUAL'])]));
    $service = app(AiChatService::class);
    $conversation = $service->startConversation($this->user, 'How?');
    $service->recordUserMessage($conversation, 'How?');

    consume($service->streamAssistantReply($conversation, $this->user, 'How?'));

    $system = $this->provider->calls[0]['messages'][0];

    expect($system['role'])->toBe('system')
        ->and($system['content'])->toStartWith('You answer questions about widgets.')
        ->and($system['content'])->toContain('Prefer the manual over forum posts.')
        ->and($system['content'])->toContain('[1] MANUAL Manual');
});

it('says nothing matched, and still answers, when retrieval finds nothing', function () {
    $service = app(AiChatService::class);
    $conversation = $service->startConversation($this->user, 'How?');
    $service->recordUserMessage($conversation, 'How?');

    consume($service->streamAssistantReply($conversation, $this->user, 'How?'));

    expect($this->provider->calls[0]['messages'][0]['content'])
        ->toStartWith(AiChatService::DEFAULT_SYSTEM_PROMPT)
        ->toContain('No specific content matched this question.');
});

it('sends the whole conversation to the provider, oldest first', function () {
    $service = app(AiChatService::class);
    $conversation = $service->startConversation($this->user, 'First');
    $service->recordUserMessage($conversation, 'First');
    consume($service->streamAssistantReply($conversation, $this->user, 'First'));
    $service->recordUserMessage($conversation, 'Second');

    consume($service->streamAssistantReply($conversation, $this->user, 'Second'));

    $roles = array_column(array_slice($this->provider->calls[1]['messages'], 1), 'role');

    expect($roles)->toBe(['user', 'assistant', 'user']);
});

it('gives retrieval what the person asked earlier, newest first', function () {
    $this->sources->register($source = new StaticSource('docs'));
    $service = app(AiChatService::class);
    $conversation = $service->startConversation($this->user, 'I use version 2');
    $service->recordUserMessage($conversation, 'I use version 2');
    $service->recordUserMessage($conversation, 'Now how do I install?');

    consume($service->streamAssistantReply($conversation, $this->user, 'Now how do I install?'));

    expect($source->lastQuery->versionHint)->toBe("Now how do I install?\nI use version 2");
});

it('keeps nothing when the provider says nothing', function () {
    $this->provider->deltas = ['  '];
    $service = app(AiChatService::class);
    $conversation = $service->startConversation($this->user, 'How?');
    $service->recordUserMessage($conversation, 'How?');

    consume($service->streamAssistantReply($conversation, $this->user, 'How?'));

    expect($conversation->messages()->count())->toBe(1);
});

it('keeps what was streamed when the reader stops reading', function () {
    $service = app(AiChatService::class);
    $conversation = $service->startConversation($this->user, 'How?');
    $service->recordUserMessage($conversation, 'How?');

    $replies = $service->streamAssistantReply($conversation, $this->user, 'How?');
    $replies->current();
    unset($replies);

    expect($conversation->messages()->latest('id')->first()->content)->toBe('Hello ');
});
