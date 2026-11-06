<?php

declare(strict_types=1);

namespace Filagora\Ai\Providers;

class OpenAiChatProvider extends AbstractOpenAiCompatibleChatProvider
{
    public function providerName(): string
    {
        return 'openai';
    }

    public function model(): string
    {
        return (string) config('filagora-ai.chat.openai.model');
    }

    protected function endpoint(): string
    {
        return 'https://api.openai.com/v1/chat/completions';
    }

    protected function apiKey(): string
    {
        return (string) config('filagora-ai.chat.openai.api_key');
    }
}
