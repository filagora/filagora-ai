<?php

declare(strict_types=1);

namespace Filagora\Ai\Tests\Support;

use Filagora\Ai\FilagoraAiPlugin;
use Filagora\Core\FilagoraCorePlugin;
use Filament\Panel;
use Filament\PanelProvider;

class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugin(FilagoraCorePlugin::make())
            ->plugin(FilagoraAiPlugin::make());
    }
}
