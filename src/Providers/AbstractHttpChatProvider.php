<?php

declare(strict_types=1);

namespace Filagora\Ai\Providers;

use Filagora\Ai\Contracts\ChatProvider;
use Filagora\Ai\Services\ChatUsageRecorder;
use Generator;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\StreamInterface;
use Throwable;

/**
 * Shared HTTP/streaming plumbing only - unlike AbstractHttpEmbeddingProvider,
 * this does NOT share one response parser across providers: Anthropic's
 * Messages API and OpenAI's Chat Completions API stream genuinely different
 * SSE frame shapes (they differ, so each provider parses its own). What's shared here
 * is just "open a streaming HTTP POST, read it line by line, hand each raw
 * line to the concrete provider, record usage/failures" - each subclass
 * still does its own real parsing via handleLine()/finalUsage().
 */
abstract class AbstractHttpChatProvider implements ChatProvider
{
    public function streamReply(array $messages, ?string $context = null, ?int $userId = null): Generator
    {
        $completed = false;
        $failed = false;
        $outputCharacters = 0;

        try {
            $response = Http::withHeaders($this->requestHeaders())
                ->withOptions(['stream' => true])
                ->timeout(120)
                ->connectTimeout(10)
                ->post($this->endpoint(), $this->requestBody($messages));

            if ($response->failed()) {
                $response->throw();
            }

            $stream = $response->toPsrResponse()->getBody();
            $buffer = '';

            foreach ($this->readLines($stream, $buffer) as $line) {
                $delta = $this->handleLine($line);
                if ($delta !== null && $delta !== '') {
                    $outputCharacters += strlen($delta);

                    yield $delta;
                } elseif (trim($line) !== '') {
                    // Something arrived but it is not text yet (a model working
                    // through its reasoning, a usage record). Pass an empty piece
                    // on so a caller can keep its own connection alive while a
                    // slow model thinks.
                    yield '';
                }
            }

            $completed = true;
        } catch (Throwable $e) {
            $failed = true;
            app(ChatUsageRecorder::class)->recordFailure($this->providerName(), $this->model(), null, $context, $userId);

            throw $e;
        } finally {
            // The consumer stopped reading (the reader closed the tab): the
            // request was still paid for, so it is recorded with an estimate.
            if (! $completed && ! $failed) {
                app(ChatUsageRecorder::class)->recordAbandoned(
                    $this->providerName(),
                    $this->model(),
                    strlen((string) json_encode($messages)),
                    $outputCharacters,
                    null,
                    $context,
                    $userId,
                );
            }
        }

        [$inputTokens, $outputTokens] = $this->finalUsage();
        app(ChatUsageRecorder::class)->recordSuccess($this->providerName(), $this->model(), $inputTokens, $outputTokens, null, $context, $userId);

        return ['input_tokens' => $inputTokens, 'output_tokens' => $outputTokens];
    }

    /**
     * @return Generator<int, string>
     */
    private function readLines(StreamInterface $stream, string &$buffer): Generator
    {
        while (! $stream->eof()) {
            $buffer .= $stream->read(4096);

            while (($newlinePos = strpos($buffer, "\n")) !== false) {
                $line = rtrim(substr($buffer, 0, $newlinePos), "\r");
                $buffer = substr($buffer, $newlinePos + 1);

                yield $line;
            }
        }

        if (trim($buffer) !== '') {
            yield rtrim($buffer, "\r");
        }
    }

    /**
     * @return array{0: ?int, 1: ?int} [inputTokens, outputTokens]
     */
    abstract protected function finalUsage(): array;

    /**
     * Handle one raw SSE line, tracking any running state (usage, current
     * event type, etc.) on the instance. Returns a text delta to yield, or
     * null if this line carries no user-visible text.
     */
    abstract protected function handleLine(string $line): ?string;

    /**
     * @param  list<array{role: 'system'|'user'|'assistant', content: string}>  $messages
     * @return array<string, mixed>
     */
    abstract protected function requestBody(array $messages): array;

    /**
     * @return array<string, string>
     */
    abstract protected function requestHeaders(): array;

    abstract protected function endpoint(): string;
}
