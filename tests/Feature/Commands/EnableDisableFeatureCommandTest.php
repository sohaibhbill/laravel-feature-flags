<?php

namespace MugiWara\FeatureFlags\Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use MugiWara\FeatureFlags\Tests\TestCase;

class EnableDisableFeatureCommandTest extends TestCase
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

    // -----------------------------------------------------------------------
    // feature:enable
    // -----------------------------------------------------------------------

    public function test_enable_creates_and_enables_flag(): void
    {
        $this->artisan('feature:enable beta_ui')
            ->assertSuccessful()
            ->expectsOutputToContain('beta_ui')
            ->expectsOutputToContain('enabled');

        $this->assertTrue(
            (bool) DB::table('feature_flags')->where('name', 'beta_ui')->value('enabled')
        );
    }

    public function test_enable_with_tenant_option(): void
    {
        $this->artisan('feature:enable beta_ui --tenant=tenant_1')
            ->assertSuccessful()
            ->expectsOutputToContain('tenant: tenant_1');

        $this->assertTrue(
            (bool) DB::table('feature_flags')
                ->where('name', 'beta_ui')
                ->where('tenant_id', 'tenant_1')
                ->value('enabled')
        );
    }

    public function test_enable_shows_warning_for_config_driver(): void
    {
        config(['features.driver' => 'config', 'features.flags' => ['my_flag' => false]]);

        $this->artisan('feature:enable my_flag')
            ->assertSuccessful()
            ->expectsOutputToContain('not persisted');
    }

    public function test_enable_fails_when_tenant_used_with_config_driver(): void
    {
        config(['features.driver' => 'config', 'features.flags' => []]);

        $this->artisan('feature:enable my_flag --tenant=tenant_1')
            ->assertFailed()
            ->expectsOutputToContain('database driver');
    }

    // -----------------------------------------------------------------------
    // feature:disable
    // -----------------------------------------------------------------------

    public function test_disable_creates_and_disables_flag(): void
    {
        $this->artisan('feature:disable beta_ui')
            ->assertSuccessful()
            ->expectsOutputToContain('beta_ui')
            ->expectsOutputToContain('disabled');

        $this->assertFalse(
            (bool) DB::table('feature_flags')->where('name', 'beta_ui')->value('enabled')
        );
    }

    public function test_disable_with_tenant_option(): void
    {
        $this->artisan('feature:disable beta_ui --tenant=tenant_2')
            ->assertSuccessful()
            ->expectsOutputToContain('tenant: tenant_2');

        $this->assertFalse(
            (bool) DB::table('feature_flags')
                ->where('name', 'beta_ui')
                ->where('tenant_id', 'tenant_2')
                ->value('enabled')
        );
    }

    public function test_disable_then_enable_updates_flag(): void
    {
        $this->artisan('feature:disable beta_ui')->assertSuccessful();
        $this->artisan('feature:enable beta_ui')->assertSuccessful();

        $this->assertTrue(
            (bool) DB::table('feature_flags')->where('name', 'beta_ui')->value('enabled')
        );
    }
}
