<?php

namespace Filagora\Ai\Tests\Unit;

use Filagora\Ai\Contracts\ChatProvider;
use Filagora\Ai\Providers\AnthropicChatProvider;
use Filagora\Ai\Providers\OpenAiChatProvider;
use Filagora\Ai\Services\ChatProviderManager;
use Filagora\Ai\Tests\TestCase;
use InvalidArgumentException;

class ChatProviderManagerTest extends TestCase
{
    public function test_resolves_the_anthropic_provider_by_default(): void
    {
        config(['filagora-ai.chat.driver' => 'anthropic']);

        $this->assertInstanceOf(AnthropicChatProvider::class, (new ChatProviderManager)->resolve());
    }

    public function test_resolves_the_openai_provider(): void
    {
        config(['filagora-ai.chat.driver' => 'openai']);

        $this->assertInstanceOf(OpenAiChatProvider::class, (new ChatProviderManager)->resolve());
    }

    public function test_a_host_can_add_a_driver_and_it_wins_over_a_built_in(): void
    {
        $custom = new class implements ChatProvider
        {
            public function providerName(): string
            {
                return 'custom';
            }

            public function model(): string
            {
                return 'm';
            }

            public function streamReply(array $messages, ?string $context = null, ?int $userId = null): \Generator
            {
                yield '';

                return ['input_tokens' => null, 'output_tokens' => null];
            }
        };
        $manager = (new ChatProviderManager)->extend('custom', fn () => $custom)->extend('openai', fn () => $custom);

        config(['filagora-ai.chat.driver' => 'custom']);
        $this->assertSame($custom, $manager->resolve());

        config(['filagora-ai.chat.driver' => 'openai']);
        $this->assertSame($custom, $manager->resolve());
    }

    public function test_an_unknown_driver_throws(): void
    {
        config(['filagora-ai.chat.driver' => 'not-a-real-driver']);

        $this->expectException(InvalidArgumentException::class);

        (new ChatProviderManager)->resolve();
    }
}
