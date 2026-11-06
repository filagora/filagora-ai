<?php

declare(strict_types=1);

namespace Filagora\Ai\Providers;

/**
 * Shared parser for chat providers whose streaming REST API follows the
 * OpenAI Chat Completions shape (`data: {choices:[{delta:{content}}]}`
 * chunks, terminated by a literal `data: [DONE]` line, with a final
 * `usage`-bearing chunk when `stream_options.include_usage` is requested) -
 * mirrors AbstractHttpEmbeddingProvider's "shared parser, thin subclasses"
 * trick, but for chat. Concrete providers below only differ on
 * endpoint/credentials/model.
 */
abstract class AbstractOpenAiCompatibleChatProvider extends AbstractHttpChatProvider
{
    private ?int $inputTokens = null;

    private ?int $outputTokens = null;

    abstract protected function apiKey(): string;

    protected function requestHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->apiKey(),
            'Content-Type' => 'application/json',
        ];
    }

    protected function requestBody(array $messages): array
    {
        return [
            'model' => $this->model(),
            'stream' => true,
            'stream_options' => ['include_usage' => true],
            'messages' => array_map(
                fn (array $message): array => ['role' => $message['role'], 'content' => $message['content']],
                $messages,
            ),
        ];
    }

    protected function handleLine(string $line): ?string
    {
        if (! str_starts_with($line, 'data: ')) {
            return null;
        }

        $payload = substr($line, 6);
        if (trim($payload) === '[DONE]') {
            return null;
        }

        $decoded = json_decode($payload, true);
        if (! is_array($decoded)) {
            return null;
        }

        if (is_array($decoded['usage'] ?? null)) {
            $this->inputTokens = is_numeric($decoded['usage']['prompt_tokens'] ?? null)
                ? (int) $decoded['usage']['prompt_tokens']
                : $this->inputTokens;
            $this->outputTokens = is_numeric($decoded['usage']['completion_tokens'] ?? null)
                ? (int) $decoded['usage']['completion_tokens']
                : $this->outputTokens;
        }

        $delta = $decoded['choices'][0]['delta']['content'] ?? null;

        return is_string($delta) ? $delta : null;
    }

    protected function finalUsage(): array
    {
        return [$this->inputTokens, $this->outputTokens];
    }
}
