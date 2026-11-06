<?php

declare(strict_types=1);

use Filagora\Ai\Models\AiChatConversation;
use Filagora\Ai\Support\Abilities;
use Filagora\Ai\Tests\Support\HostAccess;
use Filagora\Ai\Tests\Support\TestUser;
use Filagora\Core\Support\GateAbilities;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    HostAccess::$granted = [];
    $this->owner = TestUser::make('Owner');
    $this->other = TestUser::make('Other');
    $this->conversation = AiChatConversation::query()->create(['user_id' => $this->owner->id, 'title' => 'Hi']);
});

function allowChat(TestUser $user): void
{
    HostAccess::$granted[$user->id] = ['ai_chat.access'];
}

it('lets nobody in without the chat entitlement, owner included', function () {
    expect(Gate::forUser($this->owner)->allows('view', $this->conversation))->toBeFalse()
        ->and(Gate::forUser($this->owner)->allows('create', AiChatConversation::class))->toBeFalse()
        ->and(Gate::forUser($this->owner)->allows(Abilities::CHAT))->toBeFalse();
});

it('lets the owner view, continue and delete their conversation', function () {
    allowChat($this->owner);

    foreach (['view', 'update', 'delete'] as $action) {
        expect(Gate::forUser($this->owner)->allows($action, $this->conversation))->toBeTrue($action);
    }

    expect(Gate::forUser($this->owner)->allows('create', AiChatConversation::class))->toBeTrue();
});

it('keeps another entitled person out of it', function () {
    allowChat($this->other);

    foreach (['view', 'update', 'delete'] as $action) {
        expect(Gate::forUser($this->other)->allows($action, $this->conversation))->toBeFalse($action);
    }
});

it('lets a host point the ability at its own key', function () {
    config(['filagora-ai.gates' => ['filagora.ai.chat' => 'host.chat']]);
    GateAbilities::define('filagora-ai.gates');
    HostAccess::$granted[$this->owner->id] = ['host.chat'];

    expect(Gate::forUser($this->owner)->allows('view', $this->conversation))->toBeTrue();
});
