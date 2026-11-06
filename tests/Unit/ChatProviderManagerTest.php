<?php

namespace Filagora\Ai\Tests\Unit;

use Filagora\Ai\Providers\AnthropicChatProvider;
use Filagora\Ai\Providers\DigitalOceanChatProvider;
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

    public function test_resolves_the_digitalocean_provider(): void
    {
        config(['filagora-ai.chat.driver' => 'digitalocean']);

        $this->assertInstanceOf(DigitalOceanChatProvider::class, (new ChatProviderManager)->resolve());
    }

    public function test_an_unknown_driver_throws(): void
    {
        config(['filagora-ai.chat.driver' => 'not-a-real-driver']);

        $this->expectException(InvalidArgumentException::class);

        (new ChatProviderManager)->resolve();
    }
}
