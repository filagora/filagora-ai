<?php

declare(strict_types=1);

namespace Filagora\Ai;

use Filagora\Ai\Filament\Resources\EmbeddingUsages\EmbeddingUsageResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

class FilagoraAiPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'filagora-ai';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            EmbeddingUsageResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
