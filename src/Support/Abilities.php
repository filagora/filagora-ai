<?php

declare(strict_types=1);

namespace Filagora\Ai\Support;

/**
 * The Gate abilities this package checks. Each maps to an entitlement key in `filagora-ai.gates`,
 * which a host can point at its own key.
 */
final class Abilities
{
    public const CHAT = 'filagora.ai.chat';

    public const VIEW_USAGE = 'filagora.ai.view-usage';
}
