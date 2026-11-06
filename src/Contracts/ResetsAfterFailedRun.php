<?php

declare(strict_types=1);

namespace Filagora\Ai\Contracts;

use Carbon\CarbonInterface;

/**
 * An index that marks rows as indexed as it sends them, and so must take the mark back when the
 * search engine then rejected, or could not be shown to have taken, what was sent. `VectorBackfill`
 * calls it after such a run, so the rows are sent again next time (sending twice is safe).
 */
interface ResetsAfterFailedRun
{
    /**
     * Mark the rows sent since `$startedAt` as not indexed.
     */
    public function resetAfterFailedRun(CarbonInterface $startedAt): void;
}
