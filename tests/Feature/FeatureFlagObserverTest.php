<?php

namespace MugiWara\FeatureFlags\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use MugiWara\FeatureFlags\Models\FeatureFlag;
use MugiWara\FeatureFlags\Tests\TestCase;

class FeatureFlagObserverTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('features.driver', 'database');
        $app['config']->set('features.cache.enabled', true);
        $app['config']->set('features.cache.prefix', 'feature_flag:');
        $app['config']->set('features.cache.ttl', 60);
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

    private function cacheKey(string $name, ?string $tenant = null): string
    {
        $tenant = $tenant ? ":{$tenant}" : '';
        return "feature_flag:{$name}{$tenant}";
    }

    private function warmCache(string $key, bool $value): void
    {
        Cache::put($key, $value, 60);
    }

    // -----------------------------------------------------------------------
    // saved (create)
    // -----------------------------------------------------------------------

    public function test_creating_a_flag_clears_its_cache_entry(): void
    {
        $key = $this->cacheKey('new_feature');
        $this->warmCache($key, false); // stale: says disabled

        FeatureFlag::create(['name' => 'new_feature', 'enabled' => true]);

        $this->assertFalse(Cache::has($key));
    }

    public function test_creating_a_tenant_flag_clears_tenant_scoped_cache(): void
    {
        $key = $this->cacheKey('new_feature', 'tenant_1');
        $this->warmCache($key, false);

        FeatureFlag::create(['name' => 'new_feature', 'enabled' => true, 'tenant_id' => 'tenant_1']);

        $this->assertFalse(Cache::has($key));
    }

    // -----------------------------------------------------------------------
    // saved (update via Eloquent)
    // -----------------------------------------------------------------------

    public function test_updating_a_flag_via_eloquent_clears_cache(): void
    {
        $flag = FeatureFlag::create(['name' => 'beta_ui', 'enabled' => false]);

        $key = $this->cacheKey('beta_ui');
        $this->warmCache($key, false); // stale: says disabled

        $flag->update(['enabled' => true]); // direct Eloquent update, bypasses driver

        $this->assertFalse(Cache::has($key));
    }

    public function test_updating_a_tenant_flag_clears_only_its_scoped_cache(): void
    {
        $flag = FeatureFlag::create(['name' => 'beta_ui', 'enabled' => false, 'tenant_id' => 'tenant_1']);

        $globalKey = $this->cacheKey('beta_ui');
        $tenantKey = $this->cacheKey('beta_ui', 'tenant_1');

        $this->warmCache($globalKey, false);
        $this->warmCache($tenantKey, false);

        $flag->update(['enabled' => true]);

        // Only the tenant-scoped key must be cleared
        $this->assertFalse(Cache::has($tenantKey));
        // Global key is untouched (it belongs to a different flag row)
        $this->assertTrue(Cache::has($globalKey));
    }

    // -----------------------------------------------------------------------
    // saved via a seeder-style DB::table (does NOT trigger observer)
    // vs. Eloquent save (DOES trigger observer)
    // This documents the exact gap the observer fills.
    // -----------------------------------------------------------------------

    public function test_driver_cache_becomes_stale_after_raw_db_update(): void
    {
        // Warm the cache saying "enabled"
        $key = $this->cacheKey('beta_ui');
        $this->warmCache($key, true);

        // Raw DB update — observer does NOT fire, cache stays stale
        \Illuminate\Support\Facades\DB::table('feature_flags')
            ->insert(['name' => 'beta_ui', 'enabled' => false, 'created_at' => now(), 'updated_at' => now()]);

        // Cache still says "true" (stale)
        $this->assertTrue(Cache::get($key));
    }

    public function test_eloquent_save_fixes_stale_cache_from_seeder(): void
    {
        // Simulate a seeder that wrote directly and left the cache stale
        \Illuminate\Support\Facades\DB::table('feature_flags')
            ->insert(['name' => 'beta_ui', 'enabled' => false, 'created_at' => now(), 'updated_at' => now()]);

        $key = $this->cacheKey('beta_ui');
        $this->warmCache($key, true); // stale: says enabled

        // An Eloquent save (e.g. via artisan feature:enable, admin panel) clears it
        FeatureFlag::where('name', 'beta_ui')->first()->update(['enabled' => true]);

        $this->assertFalse(Cache::has($key));
    }

    // -----------------------------------------------------------------------
    // deleted
    // -----------------------------------------------------------------------

    public function test_deleting_a_flag_clears_its_cache(): void
    {
        $flag = FeatureFlag::create(['name' => 'old_feature', 'enabled' => true]);

        $key = $this->cacheKey('old_feature');
        $this->warmCache($key, true);

        $flag->delete();

        $this->assertFalse(Cache::has($key));
    }

    public function test_deleting_a_tenant_flag_clears_its_scoped_cache(): void
    {
        $flag = FeatureFlag::create(['name' => 'old_feature', 'enabled' => true, 'tenant_id' => 'tenant_2']);

        $key = $this->cacheKey('old_feature', 'tenant_2');
        $this->warmCache($key, true);

        $flag->delete();

        $this->assertFalse(Cache::has($key));
    }

    // -----------------------------------------------------------------------
    // Cache disabled — observer must be a no-op
    // -----------------------------------------------------------------------

    public function test_observer_is_no_op_when_cache_disabled(): void
    {
        config(['features.cache.enabled' => false]);

        $key = $this->cacheKey('beta_ui');
        $this->warmCache($key, true);

        FeatureFlag::create(['name' => 'beta_ui', 'enabled' => false]);

        // Cache should be untouched because the observer skips when cache is off
        $this->assertTrue(Cache::has($key));
    }
}
