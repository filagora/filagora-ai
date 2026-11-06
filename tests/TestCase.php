<?php

declare(strict_types=1);

namespace Filagora\Ai\Tests;

use Filagora\Ai\AiServiceProvider;
use Filagora\Ai\Tests\Support\HostAccessServiceProvider;
use Filagora\Ai\Tests\Support\TestSupportServiceProvider;
use Filagora\Ai\Tests\Support\TestUser;
use Filagora\Core\CoreServiceProvider;
use Filagora\Search\SearchServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            CoreServiceProvider::class,
            AiServiceProvider::class,
            SearchServiceProvider::class,
            HostAccessServiceProvider::class,
            TestSupportServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('filagora.user_model', TestUser::class);
        $app['config']->set('auth.providers.users.model', TestUser::class);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
    }
}
