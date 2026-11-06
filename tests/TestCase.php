<?php

declare(strict_types=1);

namespace Filagora\Ai\Tests;

use Filagora\Ai\AiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            AiServiceProvider::class,
        ];
    }
}
