<?php

declare(strict_types=1);

namespace Filagora\Ai\Policies;

use Filagora\Ai\Models\AiChatConversation;
use Filagora\Ai\Support\Abilities;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A conversation belongs to the person who started it, and only while they may still use the chat.
 */
class AiChatConversationPolicy
{
    public function __construct(private readonly Gate $gate) {}

    public function viewAny(Authenticatable $user): bool
    {
        return $this->canChat($user);
    }

    public function view(Authenticatable $user, AiChatConversation $conversation): bool
    {
        return $this->owns($user, $conversation);
    }

    public function create(Authenticatable $user): bool
    {
        return $this->canChat($user);
    }

    public function update(Authenticatable $user, AiChatConversation $conversation): bool
    {
        return $this->owns($user, $conversation);
    }

    public function delete(Authenticatable $user, AiChatConversation $conversation): bool
    {
        return $this->owns($user, $conversation);
    }

    private function canChat(Authenticatable $user): bool
    {
        return $this->gate->forUser($user)->allows(Abilities::CHAT);
    }

    private function owns(Authenticatable $user, AiChatConversation $conversation): bool
    {
        return (string) $conversation->user_id === (string) $user->getAuthIdentifier() && $this->canChat($user);
    }
}
