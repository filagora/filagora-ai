<?php

declare(strict_types=1);

use Filagora\Ai\FilagoraAiPlugin;
use Filament\Panel;

it('merges the package config', function () {
    expect(config('filagora-ai.navigation_group'))->toBe('AI');
});

it('registers on a panel', function () {
    $panel = Panel::make()->id('admin')->plugin(FilagoraAiPlugin::make());

    expect($panel->hasPlugin('filagora-ai'))->toBeTrue();
});
