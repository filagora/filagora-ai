<?php

declare(strict_types=1);

namespace Filagora\Ai;

use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/filagora-ai.php', 'filagora-ai');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filagora-ai');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filagora-ai');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/filagora-ai.php' => config_path('filagora-ai.php'),
            ], 'filagora-ai-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'filagora-ai-migrations');
        }
    }
}
