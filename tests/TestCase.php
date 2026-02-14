<?php

namespace MugiWara\FeatureFlags\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use MugiWara\FeatureFlags\FeatureFlagsServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [FeatureFlagsServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Feature' => \MugiWara\FeatureFlags\Facades\Feature::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Use config driver and disable cache by default in all tests.
        // Individual tests that need the database driver or caching
        // override these via defineEnvironment() or direct config calls.
        $app['config']->set('features.driver', 'config');
        $app['config']->set('features.flags', []);
        $app['config']->set('features.cache.enabled', false);
    }
}
