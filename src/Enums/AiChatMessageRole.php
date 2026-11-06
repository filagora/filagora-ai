<?php

declare(strict_types=1);

namespace Filagora\Ai\Enums;

enum AiChatMessageRole: string
{
    case USER = 'user';
    case ASSISTANT = 'assistant';

    public function label(): string
    {
        return match ($this) {
            self::USER => 'You',
            self::ASSISTANT => 'Assistant',
        };
    }
}
