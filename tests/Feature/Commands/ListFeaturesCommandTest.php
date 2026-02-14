<?php

namespace MugiWara\FeatureFlags\Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use MugiWara\FeatureFlags\Tests\TestCase;

class ListFeaturesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('features.driver', 'database');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
    }

    public function test_shows_info_when_no_flags_exist(): void
    {
        $this->artisan('feature:list')
            ->assertSuccessful()
            ->expectsOutputToContain('No feature flags found');
    }

    public function test_lists_global_flags_in_table(): void
    {
        DB::table('feature_flags')->insert([
            ['name' => 'beta_ui',   'enabled' => true,  'tenant_id' => null, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'dark_mode', 'enabled' => false, 'tenant_id' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->artisan('feature:list')
            ->assertSuccessful()
            ->expectsOutputToContain('beta_ui')
            ->expectsOutputToContain('dark_mode');
    }

    public function test_filters_by_tenant_option(): void
    {
        DB::table('feature_flags')->insert([
            ['name' => 'beta_ui', 'enabled' => true,  'tenant_id' => 'tenant_1', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'beta_ui', 'enabled' => false, 'tenant_id' => 'tenant_2', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->artisan('feature:list --tenant=tenant_1')
            ->assertSuccessful()
            ->expectsOutputToContain('beta_ui');
    }

    public function test_config_driver_shows_flags_from_config(): void
    {
        // Override to config driver for this test
        config(['features.driver' => 'config', 'features.flags' => ['my_flag' => true]]);

        $this->artisan('feature:list')
            ->assertSuccessful()
            ->expectsOutputToContain('my_flag');
    }

    public function test_config_driver_shows_persistence_warning(): void
    {
        config(['features.driver' => 'config', 'features.flags' => ['my_flag' => false]]);

        $this->artisan('feature:list')
            ->assertSuccessful()
            ->expectsOutputToContain('config driver');
    }
}
