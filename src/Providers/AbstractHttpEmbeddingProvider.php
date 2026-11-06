<?php

declare(strict_types=1);

namespace Filagora\Ai\Providers;

use Filagora\Ai\Contracts\EmbeddingProvider;
use Filagora\Ai\Services\EmbeddingUsageRecorder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Shared HTTP plumbing for embedding providers whose REST API follows the
 * OpenAI `/v1/embeddings` request/response shape
 * (`{model, input}` -> `{data: [{embedding, index}, ...]}`) - Voyage AI
 * deliberately mirrors it, so both concrete providers below only need to
 * supply their endpoint/credentials/model.
 */
abstract class AbstractHttpEmbeddingProvider implements EmbeddingProvider
{
    public function embed(string $text, ?string $context = null): array
    {
        return $this->embedBatch([$text], $context)[0];
    }

    public function embedBatch(array $texts, ?string $context = null): array
    {
        try {
            $response = Http::withToken($this->apiKey())
                ->timeout(10)
                ->connectTimeout(5)
                ->post($this->endpoint(), [
                    'model' => $this->model(),
                    'input' => $texts,
                ])
                ->throw();
        } catch (RequestException $e) {
            app(EmbeddingUsageRecorder::class)->recordFailure($this->providerName(), $this->model(), $context);

            throw $e;
        }

        $inputTokens = (int) $response->json('usage.total_tokens', 0);
        app(EmbeddingUsageRecorder::class)->recordSuccess($this->providerName(), $this->model(), $inputTokens, $context);

        /** @var list<array{index: int, embedding: list<float>}> $data */
        $data = $response->json('data', []);

        return collect($data)
            ->sortBy('index')
            ->pluck('embedding')
            ->values()
            ->all();
    }

    /**
     * Why an embeddings request failed, in words that say who is at fault. The
     * raw cURL line ("Operation timed out after 10002 milliseconds with 0 bytes
     * received") is true but does not tell an operator whether to look at the
     * network, the key, the account or the provider.
     *
     * It says only what was observed: the HTTP status if the provider answered;
     * otherwise, for a connection failure, whether a plain unauthenticated
     * request to the same host gets an answer. It never includes the key.
     */
    public function describeFailure(Throwable $failure): string
    {
        $host = (string) (parse_url($this->endpoint(), PHP_URL_HOST) ?: $this->endpoint());

        if ($failure instanceof RequestException) {
            $status = $failure->response->status();

            return sprintf('%s answered the embeddings request with HTTP %d%s.', $host, $status, $this->statusHint($status));
        }

        if (! $failure instanceof ConnectionException) {
            return 'the embeddings request failed: '.$failure->getMessage();
        }

        $timedOut = str_contains(strtolower($failure->getMessage()), 'timed out');
        $probe = $this->probeHost();

        if ($probe === null) {
            return sprintf(
                '%s could not be reached at all%s: a network, DNS or provider outage between here and there.',
                $host,
                $timedOut ? ' (the connection timed out)' : '',
            );
        }

        return sprintf(
            '%s is reachable (a plain request to it got HTTP %d in %d ms), but the embeddings request %s. That points to the provider\'s embeddings service itself, not the network or the key.',
            $host,
            $probe['status'],
            $probe['ms'],
            $timedOut ? 'got no response before it timed out' : 'failed: '.$failure->getMessage(),
        );
    }

    /**
     * Whether the host answers anything at all, without sending the key.
     *
     * @return array{status: int, ms: int}|null null if it did not answer
     */
    private function probeHost(): ?array
    {
        $parts = parse_url($this->endpoint());
        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $startedAt = microtime(true);

        try {
            $response = Http::timeout(8)->connectTimeout(5)->get($parts['scheme'].'://'.$parts['host'].'/');
        } catch (Throwable) {
            return null;
        }

        return ['status' => $response->status(), 'ms' => (int) round((microtime(true) - $startedAt) * 1000)];
    }

    private function statusHint(int $status): string
    {
        return match (true) {
            $status === 401 => ' (usually: the key is wrong or has been revoked)',
            $status === 402 => ' (payment required: check the provider account balance)',
            $status === 403 => ' (usually: the key is not allowed to use this model)',
            $status === 404 => ' (the endpoint or model was not found)',
            $status === 429 => ' (rate limited: slow down and try again later)',
            $status >= 500 => ' (an error on the provider\'s side)',
            default => '',
        };
    }

    abstract protected function providerName(): string;

    abstract protected function endpoint(): string;

    abstract protected function apiKey(): string;

    abstract protected function model(): string;
}
