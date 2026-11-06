<?php

declare(strict_types=1);

namespace Filagora\Ai\Providers;

/**
 * Claude via the Messages API (https://docs.anthropic.com/en/api/messages-streaming),
 * streamed as named SSE events - message_start (carries input_tokens),
 * content_block_delta (carries each text_delta), message_delta (carries the
 * running output_tokens count, most current in its final occurrence before
 * message_stop). This does NOT share a parser with the OpenAI-compatible
 * providers below - Anthropic's event/data framing is a genuinely different
 * wire shape.
 */
class AnthropicChatProvider extends AbstractHttpChatProvider
{
    private ?string $pendingEvent = null;

    private ?int $inputTokens = null;

    private ?int $outputTokens = null;

    public function providerName(): string
    {
        return 'anthropic';
    }

    public function model(): string
    {
        return (string) config('filagora-ai.chat.anthropic.model');
    }

    protected function endpoint(): string
    {
        return 'https://api.anthropic.com/v1/messages';
    }

    protected function requestHeaders(): array
    {
        return [
            'x-api-key' => (string) config('filagora-ai.chat.anthropic.api_key'),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ];
    }

    protected function requestBody(array $messages): array
    {
        $systemText = collect($messages)
            ->where('role', 'system')
            ->pluck('content')
            ->implode("\n\n");

        $conversation = collect($messages)
            ->reject(fn (array $message): bool => $message['role'] === 'system')
            ->map(fn (array $message): array => ['role' => $message['role'], 'content' => $message['content']])
            ->values()
            ->all();

        $body = [
            'model' => $this->model(),
            'max_tokens' => (int) config('filagora-ai.chat.anthropic.max_tokens', 1024),
            'stream' => true,
            'messages' => $conversation,
        ];

        if ($systemText !== '') {
            $body['system'] = $systemText;
        }

        return $body;
    }

    protected function handleLine(string $line): ?string
    {
        if (str_starts_with($line, 'event: ')) {
            $this->pendingEvent = substr($line, 7);

            return null;
        }

        if (! str_starts_with($line, 'data: ')) {
            return null;
        }

        $payload = json_decode(substr($line, 6), true);
        if (! is_array($payload)) {
            return null;
        }

        return match ($this->pendingEvent) {
            'message_start' => $this->captureInputTokens($payload),
            'content_block_delta' => $this->extractTextDelta($payload),
            'message_delta' => $this->captureOutputTokens($payload),
            default => null,
        };
    }

    protected function finalUsage(): array
    {
        return [$this->inputTokens, $this->outputTokens];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function captureInputTokens(array $payload): null
    {
        $tokens = $payload['message']['usage']['input_tokens'] ?? null;
        if (is_numeric($tokens)) {
            $this->inputTokens = (int) $tokens;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function captureOutputTokens(array $payload): null
    {
        $tokens = $payload['usage']['output_tokens'] ?? null;
        if (is_numeric($tokens)) {
            $this->outputTokens = (int) $tokens;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractTextDelta(array $payload): ?string
    {
        if (($payload['delta']['type'] ?? null) !== 'text_delta') {
            return null;
        }

        $text = $payload['delta']['text'] ?? null;

        return is_string($text) ? $text : null;
    }
}
