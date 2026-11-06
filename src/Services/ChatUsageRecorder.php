<?php

declare(strict_types=1);

namespace Filagora\Ai\Services;

use Filagora\Ai\Models\AiChatUsage;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Logs every chat completion call for cost/usage analytics - mirrors
 * EmbeddingUsageRecorder, but tracks input and output tokens separately
 * since chat completions price them differently (embeddings only ever
 * have input tokens). Failures here are swallowed - metering must never be
 * the reason a chat reply (or the feature using it) fails.
 */
class ChatUsageRecorder
{
    /**
     * Characters per token when a cut-off call has to be estimated from text.
     * Deliberately low (so tokens are over- rather than under-counted): the
     * point of counting a call the user walked away from is that it was paid for.
     */
    private const CHARS_PER_TOKEN = 3;

    public function recordSuccess(
        string $provider,
        string $model,
        ?int $inputTokens,
        ?int $outputTokens,
        ?int $conversationId,
        ?string $context,
        ?int $userId = null,
    ): void {
        [$cost, $basis] = $this->estimateCost($provider, $model, $inputTokens, $outputTokens);

        $this->record([
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'provider' => $provider,
            'model' => $model,
            'context' => $context,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'estimated_cost_usd' => $cost,
            'cost_basis' => $basis,
            'succeeded' => true,
        ]);
    }

    public function recordFailure(string $provider, string $model, ?int $conversationId, ?string $context, ?int $userId = null): void
    {
        $this->record([
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'provider' => $provider,
            'model' => $model,
            'context' => $context,
            'input_tokens' => null,
            'output_tokens' => null,
            'estimated_cost_usd' => null,
            'cost_basis' => 'none',
            'succeeded' => false,
        ]);
    }

    /**
     * A stream that stopped before it finished - the reader closed the tab, say.
     * The provider has been paid for the input and for what it generated, but
     * reports no totals, so they are estimated from the text. Recorded as not
     * succeeded, yet with a cost: a limit that ignored it could be avoided by
     * closing the tab.
     */
    public function recordAbandoned(
        string $provider,
        string $model,
        int $inputCharacters,
        int $outputCharacters,
        ?int $conversationId,
        ?string $context,
        ?int $userId = null,
    ): void {
        $inputTokens = (int) ceil($inputCharacters / self::CHARS_PER_TOKEN);
        $outputTokens = (int) ceil($outputCharacters / self::CHARS_PER_TOKEN);
        [$cost] = $this->estimateCost($provider, $model, $inputTokens, $outputTokens);

        $this->record([
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'provider' => $provider,
            'model' => $model,
            'context' => $context,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'estimated_cost_usd' => $cost,
            'cost_basis' => 'estimated_tokens',
            'succeeded' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function record(array $attributes): void
    {
        try {
            AiChatUsage::query()->create($attributes);
        } catch (Throwable $e) {
            Log::warning('Failed to record AI chat usage.', [
                'attributes' => $attributes,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * A model with no configured price is costed at a conservative fallback
     * rather than left without a cost: a null cost would count as free against
     * any allowance. Without token counts there is nothing to cost.
     *
     * @return array{0: ?float, 1: string} the cost and how it was arrived at
     */
    private function estimateCost(string $provider, string $model, ?int $inputTokens, ?int $outputTokens): array
    {
        if ($inputTokens === null || $outputTokens === null) {
            return [null, 'none'];
        }

        $inputRate = config("filagora-ai.chat.cost_per_million_tokens.{$provider}.{$model}.input");
        $outputRate = config("filagora-ai.chat.cost_per_million_tokens.{$provider}.{$model}.output");
        $basis = 'reported';

        if (! is_numeric($inputRate) || ! is_numeric($outputRate)) {
            $inputRate = config('filagora-ai.chat.fallback_cost_per_million_tokens.input');
            $outputRate = config('filagora-ai.chat.fallback_cost_per_million_tokens.output');
            $basis = 'fallback_price';

            if (! is_numeric($inputRate) || ! is_numeric($outputRate)) {
                return [null, 'none'];
            }
        }

        $cost = ($inputTokens / 1_000_000) * (float) $inputRate
            + ($outputTokens / 1_000_000) * (float) $outputRate;

        return [round($cost, 6), $basis];
    }
}
