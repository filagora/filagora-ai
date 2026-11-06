<?php

declare(strict_types=1);

namespace Filagora\Ai\Services;

use Closure;
use Filagora\Ai\Contracts\ChatProvider;
use Filagora\Ai\Providers\AnthropicChatProvider;
use Filagora\Ai\Providers\OpenAiChatProvider;
use InvalidArgumentException;

/**
 * Picks the chat provider named by `filagora-ai.chat.driver`. A host adds its own with
 * `extend()`, which takes precedence over a built-in of the same name.
 */
class ChatProviderManager
{
    /** @var array<string, Closure(): ChatProvider> */
    private array $extensions = [];

    /**
     * @param  Closure(): ChatProvider  $factory
     */
    public function extend(string $driver, Closure $factory): static
    {
        $this->extensions[$driver] = $factory;

        return $this;
    }

    public function resolve(): ChatProvider
    {
        $driver = (string) config('filagora-ai.chat.driver', 'anthropic');

        if (isset($this->extensions[$driver])) {
            return ($this->extensions[$driver])();
        }

        return match ($driver) {
            'anthropic' => new AnthropicChatProvider,
            'openai' => new OpenAiChatProvider,
            default => throw new InvalidArgumentException("Unknown AI chat driver [{$driver}]."),
        };
    }
}
