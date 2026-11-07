<?php

declare(strict_types=1);

namespace Filagora\Ai\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Where a person's monthly AI window starts, when they pay by the month or the year: the date their
 * subscription bills on. The package knows no billing system; a host that has one binds this. With
 * nothing bound the window is the calendar month.
 */
interface BillingAnchor
{
    /**
     * The billing date to count months from, or null if there is none (a person who pays nothing, or
     * staff, are counted by the calendar month).
     *
     * @param  list<string>  $preferEntitlements  if the person has several subscriptions, prefer the one
     *                                            that grants any of these: the plan that grants the AI
     *                                            access sets the date
     */
    public function for(Authenticatable $user, array $preferEntitlements): ?CarbonInterface;
}
