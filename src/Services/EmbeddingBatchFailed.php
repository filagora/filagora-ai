<?php

declare(strict_types=1);

namespace Filagora\Ai\Services;

use RuntimeException;
use Throwable;

/**
 * A batch of embeddings could not be made, raised (instead of quietly indexing
 * the batch without vectors) when a caller has asked for that - see
 * ThreadVector::failOnEmbeddingError().
 */
final class EmbeddingBatchFailed extends RuntimeException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
