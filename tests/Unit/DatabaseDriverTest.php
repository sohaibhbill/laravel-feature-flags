<?php

namespace MugiWara\FeatureFlags\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use MugiWara\FeatureFlags\Drivers\DatabaseDriver;
use MugiWara\FeatureFlags\Tests\TestCase;

class DatabaseDriverTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    private function driver(array $extra = []): DatabaseDriver
    {
        return new DatabaseDriver(array_merge([
            'cache' => ['enabled' => false],
        ], $extra));
    }

    // -----------------------------------------------------------------------
    // Basic enable / disable
    // -----------------------------------------------------------------------

    public function test_returns_false_for_unknown_flag(): void
    {
        $this->assertFalse($this->driver()->isEnabled('unknown'));
    }

    public function test_enable_creates_and_enables_flag(): void
    {
        $driver = $this->driver();
        $driver->enable('new_feature');

        $this->assertTrue($driver->isEnabled('new_feature'));
    }

    public function test_disable_creates_and_disables_flag(): void
    {
        $driver = $this->driver();
        $driver->disable('new_feature');

        $this->assertFalse($driver->isEnabled('new_feature'));
    }

    public function test_enable_then_disable_returns_false(): void
    {
        $driver = $this->driver();
        $driver->enable('my_feature');
        $driver->disable('my_feature');

        $this->assertFalse($driver->isEnabled('my_feature'));
    }

    public function test_disable_then_enable_returns_true(): void
    {
        $driver = $this->driver();
        $driver->disable('my_feature');
        $driver->enable('my_feature');

        $this->assertTrue($driver->isEnabled('my_feature'));
    }

    // -----------------------------------------------------------------------
    // Multi-tenancy
    // -----------------------------------------------------------------------

    public function test_returns_global_flag_when_no_tenant_set(): void
    {
        $driver = $this->driver();
        $driver->enable('beta');   // inserts with tenant_id = null

        $this->assertTrue($driver->isEnabled('beta'));
    }

    public function test_tenant_specific_flag_overrides_global(): void
    {
        $driver = $this->driver();
        $driver->disable('beta');          // global: off

        $driver->setTenant('tenant_1');
        $driver->enable('beta');           // tenant_1: on

        $this->assertTrue($driver->isEnabled('beta'));
    }

    public function test_global_flag_used_as_fallback_when_tenant_has_no_override(): void
    {
        // Global flag = on, tenant has no row of its own
        $globalDriver = $this->driver();
        $globalDriver->enable('beta');

        $tenantDriver = $this->driver();
        $tenantDriver->setTenant('tenant_99');

        $this->assertTrue($tenantDriver->isEnabled('beta'));
    }

    public function test_tenant_flag_does_not_affect_other_tenants(): void
    {
        $driver = $this->driver();
        $driver->setTenant('tenant_1');
        $driver->enable('beta');

        $driver->setTenant('tenant_2');
        // tenant_2 has no override; global doesn't exist either
        $this->assertFalse($driver->isEnabled('beta'));
    }

    public function test_set_tenant_null_reverts_to_global_scope(): void
    {
        $driver = $this->driver();
        $driver->enable('beta');       // global: on

        $driver->setTenant('tenant_1');
        $driver->disable('beta');      // tenant_1: off

        $driver->setTenant(null);      // back to global
        $this->assertTrue($driver->isEnabled('beta'));
    }

    // -----------------------------------------------------------------------
    // Caching
    // -----------------------------------------------------------------------

    public function test_caching_does_not_break_is_enabled(): void
    {
        $driver = $this->driver(['cache' => [
            'enabled' => true,
            'ttl'     => 60,
            'prefix'  => 'test_ff:',
        ]]);

        $driver->enable('cached_feature');

        $this->assertTrue($driver->isEnabled('cached_feature'));
    }

    public function test_cache_is_cleared_after_disable(): void
    {
        $driver = $this->driver(['cache' => [
            'enabled' => true,
            'ttl'     => 60,
            'prefix'  => 'test_ff:',
        ]]);

        $driver->enable('cached_feature');
        $driver->isEnabled('cached_feature');  // populate cache

        $driver->disable('cached_feature');     // should clear cache

        $this->assertFalse($driver->isEnabled('cached_feature'));
    }

    public function test_cache_is_cleared_after_enable(): void
    {
        $driver = $this->driver(['cache' => [
            'enabled' => true,
            'ttl'     => 60,
            'prefix'  => 'test_ff:',
        ]]);

        $driver->disable('cached_feature');
        $driver->isEnabled('cached_feature');  // populate cache

        $driver->enable('cached_feature');      // should clear cache

        $this->assertTrue($driver->isEnabled('cached_feature'));
    }

    // -----------------------------------------------------------------------
    // Percentage rollout
    // -----------------------------------------------------------------------

    public function test_enabled_flag_without_percentage_metadata_returns_true(): void
    {
        $driver = $this->driver();
        $driver->enable('rollout_feature');

        $this->assertTrue($driver->isEnabled('rollout_feature'));
    }

    public function test_percentage_rollout_returns_false_when_no_identifier_set(): void
    {
        // Flag is enabled with 100% rollout but no identifier — falls through to true
        // because we cannot make a per-user decision without one.
        $driver = $this->driver();
        \Illuminate\Support\Facades\DB::table('feature_flags')->insert([
            'name'       => 'rollout_feature',
            'enabled'    => true,
            'tenant_id'  => null,
            'metadata'   => json_encode(['percentage' => 50]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // No setIdentifier() called → falls through to true
        $this->assertTrue($driver->isEnabled('rollout_feature'));
    }

    public function test_percentage_100_always_enables_with_identifier(): void
    {
        $driver = $this->driver();
        $driver->setIdentifier('user_42');
        \Illuminate\Support\Facades\DB::table('feature_flags')->insert([
            'name'       => 'rollout_feature',
            'enabled'    => true,
            'tenant_id'  => null,
            'metadata'   => json_encode(['percentage' => 100]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue($driver->isEnabled('rollout_feature'));
    }

    public function test_percentage_0_always_disables_with_identifier(): void
    {
        $driver = $this->driver();
        $driver->setIdentifier('user_42');
        \Illuminate\Support\Facades\DB::table('feature_flags')->insert([
            'name'       => 'rollout_feature',
            'enabled'    => true,
            'tenant_id'  => null,
            'metadata'   => json_encode(['percentage' => 0]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertFalse($driver->isEnabled('rollout_feature'));
    }

    public function test_same_identifier_always_gets_same_result(): void
    {
        $driver = $this->driver();
        $driver->setIdentifier('user_99');
        \Illuminate\Support\Facades\DB::table('feature_flags')->insert([
            'name'       => 'rollout_feature',
            'enabled'    => true,
            'tenant_id'  => null,
            'metadata'   => json_encode(['percentage' => 50]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $first  = $driver->isEnabled('rollout_feature');
        $second = $driver->isEnabled('rollout_feature');

        $this->assertSame($first, $second);
    }

    public function test_disabled_flag_with_percentage_metadata_still_returns_false(): void
    {
        $driver = $this->driver();
        $driver->setIdentifier('user_1');
        \Illuminate\Support\Facades\DB::table('feature_flags')->insert([
            'name'       => 'rollout_feature',
            'enabled'    => false,           // flag itself is off
            'tenant_id'  => null,
            'metadata'   => json_encode(['percentage' => 100]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // enabled=false must short-circuit before the strategy is ever consulted
        $this->assertFalse($driver->isEnabled('rollout_feature'));
    }
}
