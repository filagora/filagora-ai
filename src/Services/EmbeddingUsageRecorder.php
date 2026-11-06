<?php

declare(strict_types=1);

namespace Filagora\Ai\Services;

use Filagora\Ai\Models\AiEmbeddingUsage;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Logs every embedding API call for cost/usage analytics (see the
 * "ai_embedding_usages" table and AiEmbeddingUsageResource in Filament).
 * Failures here are swallowed - metering must never be the reason an
 * embedding call (or the feature using it) fails.
 */
class EmbeddingUsageRecorder
{
    public function recordSuccess(string $provider, string $model, int $inputTokens, ?string $context): void
    {
        $this->record([
            'provider' => $provider,
            'model' => $model,
            'context' => $context,
            'input_tokens' => $inputTokens,
            'estimated_cost_usd' => $this->estimateCost($provider, $model, $inputTokens),
            'succeeded' => true,
        ]);
    }

    public function recordFailure(string $provider, string $model, ?string $context): void
    {
        $this->record([
            'provider' => $provider,
            'model' => $model,
            'context' => $context,
            'input_tokens' => null,
            'estimated_cost_usd' => null,
            'succeeded' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function record(array $attributes): void
    {
        try {
            AiEmbeddingUsage::query()->create($attributes);
        } catch (Throwable $e) {
            Log::warning('Failed to record AI embedding usage.', [
                'attributes' => $attributes,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function estimateCost(string $provider, string $model, int $inputTokens): ?float
    {
        $ratePerMillionTokens = config("filagora-ai.embeddings.cost_per_million_tokens.{$provider}.{$model}");
        if (! is_numeric($ratePerMillionTokens)) {
            return null;
        }

        return round(($inputTokens / 1_000_000) * (float) $ratePerMillionTokens, 6);
    }
}
