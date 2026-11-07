<?php

declare(strict_types=1);

namespace Filagora\Ai\Usage;

use Carbon\CarbonInterface;
use Filagora\Ai\Contracts\BillingAnchor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The default `BillingAnchor`: nobody has a billing date, so everyone is counted by the calendar month.
 */
final class CalendarMonth implements BillingAnchor
{
    public function for(Authenticatable $user, array $preferEntitlements): ?CarbonInterface
    {
        return null;
    }
}
