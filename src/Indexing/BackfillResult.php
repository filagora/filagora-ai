<?php

declare(strict_types=1);

namespace Filagora\Ai\Indexing;

use Throwable;

/**
 * What a vector backfill run did and why it stopped.
 */
final readonly class BackfillResult
{
    public function __construct(
        public BackfillStop $stop,
        public int $sent = 0,
        public int|string|null $lastKey = null,
        public ?string $message = null,
        public int $tokens = 0,
        public float $costUsd = 0.0,
        public int $seconds = 0,
        public ?Throwable $failure = null,
    ) {}

    public function succeeded(): bool
    {
        return $this->stop === BackfillStop::COMPLETED;
    }
}
