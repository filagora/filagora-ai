<?php

declare(strict_types=1);

namespace Filagora\Ai\Usage;

use Carbon\CarbonInterface;

/**
 * One person's AI use in one window, as a page can show it.
 *
 * `percent` is null until an allowance exists to measure against: a bare count is all that can honestly
 * be said before then. It is a percentage, not an amount, so it reads the same for people who do not
 * pay in dollars.
 */
final readonly class UsageMeter
{
    public function __construct(
        public int $answers,
        public CarbonInterface $from,
        public CarbonInterface $until,
        public ?int $percent,
    ) {}

    public function hasAllowance(): bool
    {
        return $this->percent !== null;
    }
}
