<?php

declare(strict_types=1);

namespace Filagora\Ai\Services;

use Filagora\Ai\Contracts\ChatProvider;
use Filagora\Ai\Providers\AnthropicChatProvider;
use Filagora\Ai\Providers\DigitalOceanChatProvider;
use Filagora\Ai\Providers\OpenAiChatProvider;
use InvalidArgumentException;

class ChatProviderManager
{
    public function resolve(): ChatProvider
    {
        $driver = (string) config('filagora-ai.chat.driver', 'anthropic');

        return match ($driver) {
            'anthropic' => new AnthropicChatProvider,
            'openai' => new OpenAiChatProvider,
            'digitalocean' => new DigitalOceanChatProvider,
            default => throw new InvalidArgumentException("Unknown AI chat driver [{$driver}]."),
        };
    }
}
