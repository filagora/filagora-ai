<?php

declare(strict_types=1);

namespace Filagora\Ai\Usage;

use Carbon\CarbonInterface;
use Filagora\Ai\Contracts\BillingAnchor;
use Filagora\Ai\Models\AiChatUsage;
use Filagora\Core\Contracts\AccessChecker;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * What a person's page says about their AI use: answers in the current window, and, once an allowance
 * is set, how much of it is gone.
 *
 * The window follows the person's billing date (`BillingAnchor`): monthly steps counted from it, so a
 * yearly plan still resets monthly. Someone with no billing date to count from, staff for one, gets the
 * calendar month. What counts as an answer (`filagora-ai.usage.features`) and what access makes the
 * meter worth showing before the first answer (`filagora-ai.usage.access_keys`) are configuration.
 */
class UsageMeterService
{
    public function __construct(
        private readonly BillingAnchor $anchor,
        private readonly AccessChecker $access,
    ) {}

    /**
     * Null when the person has no AI access and has used none this window: the page then says nothing
     * about AI.
     */
    public function forUser(Authenticatable $user, ?CarbonInterface $now = null): ?UsageMeter
    {
        $now ??= now();
        [$from, $until] = $this->window($user, $now);

        $answers = $this->answersIn($user, $from, $until);

        if ($answers === 0 && ! $this->access->allowsAny($user, $this->accessKeys())) {
            return null;
        }

        return new UsageMeter($answers, $from, $until, $this->percentUsed($user, $from, $until));
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public function window(Authenticatable $user, CarbonInterface $now): array
    {
        $anchor = $this->anchor->for($user, $this->accessKeys());

        if ($anchor === null || $anchor->greaterThan($now)) {
            return [$now->copy()->startOfMonth(), $now->copy()->startOfMonth()->addMonth()];
        }

        // Whole months since the anchor, each counted from the anchor itself (never from the month
        // before) so the 31st does not drift to the 28th. Walking the months, rather than trusting a
        // stored period end, means a delayed webhook or a yearly plan still lands on the right month.
        $months = 0;

        while ($months < 1200 && $anchor->copy()->addMonthsNoOverflow($months + 1)->lessThanOrEqualTo($now)) {
            $months++;
        }

        return [$anchor->copy()->addMonthsNoOverflow($months), $anchor->copy()->addMonthsNoOverflow($months + 1)];
    }

    /**
     * @return list<string>
     */
    private function accessKeys(): array
    {
        return array_values(array_filter((array) config('filagora-ai.usage.access_keys', []), 'is_string'));
    }

    /**
     * @return list<string>
     */
    private function features(): array
    {
        return array_values(array_filter((array) config('filagora-ai.usage.features', []), 'is_string'));
    }

    private function answersIn(Authenticatable $user, CarbonInterface $from, CarbonInterface $until): int
    {
        return AiChatUsage::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->whereIn('context', $this->features())
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $until)
            // A reply the reader walked away from was still generated and paid for.
            ->where(fn ($query) => $query->where('succeeded', true)->orWhere('cost_basis', 'estimated_tokens'))
            ->count();
    }

    /**
     * Share of the allowance spent in the window, as a whole percentage, or null while no allowance
     * exists. The allowance (`filagora-ai.chat.allowance_usd`) is a stand-in until plans carry their
     * own: one site-wide figure, off by default, in the same cost unit the usage log records.
     */
    private function percentUsed(Authenticatable $user, CarbonInterface $from, CarbonInterface $until): ?int
    {
        $allowance = config('filagora-ai.chat.allowance_usd');

        if (! is_numeric($allowance) || (float) $allowance <= 0) {
            return null;
        }

        $spent = (float) AiChatUsage::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->whereIn('context', $this->features())
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $until)
            ->sum('estimated_cost_usd');

        return (int) min(999, floor(100 * $spent / (float) $allowance));
    }
}
