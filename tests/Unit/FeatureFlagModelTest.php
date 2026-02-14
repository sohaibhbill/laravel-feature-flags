<?php

namespace MugiWara\FeatureFlags\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use MugiWara\FeatureFlags\Models\FeatureFlag;
use MugiWara\FeatureFlags\Tests\TestCase;

class FeatureFlagModelTest extends TestCase
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

    // -----------------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------------

    public function test_scope_global_returns_only_null_tenant_flags(): void
    {
        FeatureFlag::create(['name' => 'global_flag',  'enabled' => true,  'tenant_id' => null]);
        FeatureFlag::create(['name' => 'tenant_flag',  'enabled' => true,  'tenant_id' => 'tenant_1']);

        $results = FeatureFlag::global()->get();

        $this->assertCount(1, $results);
        $this->assertSame('global_flag', $results->first()->name);
    }

    public function test_scope_for_tenant_returns_only_matching_tenant_flags(): void
    {
        FeatureFlag::create(['name' => 'flag_a', 'enabled' => true, 'tenant_id' => 'tenant_1']);
        FeatureFlag::create(['name' => 'flag_b', 'enabled' => true, 'tenant_id' => 'tenant_2']);

        $results = FeatureFlag::forTenant('tenant_1')->get();

        $this->assertCount(1, $results);
        $this->assertSame('flag_a', $results->first()->name);
    }

    public function test_scope_enabled_returns_only_enabled_flags(): void
    {
        FeatureFlag::create(['name' => 'on',  'enabled' => true]);
        FeatureFlag::create(['name' => 'off', 'enabled' => false]);

        $results = FeatureFlag::enabled()->get();

        $this->assertCount(1, $results);
        $this->assertSame('on', $results->first()->name);
    }

    public function test_scope_disabled_returns_only_disabled_flags(): void
    {
        FeatureFlag::create(['name' => 'on',  'enabled' => true]);
        FeatureFlag::create(['name' => 'off', 'enabled' => false]);

        $results = FeatureFlag::disabled()->get();

        $this->assertCount(1, $results);
        $this->assertSame('off', $results->first()->name);
    }

    // -----------------------------------------------------------------------
    // Metadata helpers
    // -----------------------------------------------------------------------

    public function test_has_percentage_rollout_true_when_metadata_has_valid_percentage(): void
    {
        $flag = new FeatureFlag(['metadata' => ['percentage' => 25]]);

        $this->assertTrue($flag->hasPercentageRollout());
    }

    public function test_has_percentage_rollout_false_when_percentage_is_zero(): void
    {
        $flag = new FeatureFlag(['metadata' => ['percentage' => 0]]);

        $this->assertFalse($flag->hasPercentageRollout());
    }

    public function test_has_percentage_rollout_false_when_percentage_is_100(): void
    {
        $flag = new FeatureFlag(['metadata' => ['percentage' => 100]]);

        $this->assertFalse($flag->hasPercentageRollout());
    }

    public function test_has_percentage_rollout_false_when_no_metadata(): void
    {
        $flag = new FeatureFlag(['metadata' => null]);

        $this->assertFalse($flag->hasPercentageRollout());
    }

    public function test_get_rollout_percentage_returns_value(): void
    {
        $flag = new FeatureFlag(['metadata' => ['percentage' => 40]]);

        $this->assertSame(40, $flag->getRolloutPercentage());
    }

    public function test_get_rollout_percentage_returns_null_when_not_set(): void
    {
        $flag = new FeatureFlag(['metadata' => []]);

        $this->assertNull($flag->getRolloutPercentage());
    }

    public function test_get_allowed_segments_returns_array(): void
    {
        $flag = new FeatureFlag(['metadata' => ['segments' => ['beta_testers', 'staff']]]);

        $this->assertSame(['beta_testers', 'staff'], $flag->getAllowedSegments());
    }

    public function test_get_allowed_segments_returns_empty_array_when_not_set(): void
    {
        $flag = new FeatureFlag(['metadata' => []]);

        $this->assertSame([], $flag->getAllowedSegments());
    }

    public function test_is_available_for_segment_true_when_segment_listed(): void
    {
        $flag = new FeatureFlag(['metadata' => ['segments' => ['beta_testers']]]);

        $this->assertTrue($flag->isAvailableForSegment('beta_testers'));
    }

    public function test_is_available_for_segment_false_when_segment_not_listed(): void
    {
        $flag = new FeatureFlag(['metadata' => ['segments' => ['beta_testers']]]);

        $this->assertFalse($flag->isAvailableForSegment('regular_users'));
    }

    public function test_is_available_for_segment_true_when_no_segments_defined(): void
    {
        // Empty segments = available to everyone
        $flag = new FeatureFlag(['metadata' => ['segments' => []]]);

        $this->assertTrue($flag->isAvailableForSegment('anyone'));
    }

    // -----------------------------------------------------------------------
    // Casts
    // -----------------------------------------------------------------------

    public function test_enabled_is_cast_to_boolean(): void
    {
        $flag = FeatureFlag::create(['name' => 'cast_test', 'enabled' => 1]);

        $this->assertIsBool($flag->fresh()->enabled);
    }

    public function test_metadata_is_cast_to_array(): void
    {
        $flag = FeatureFlag::create([
            'name'     => 'meta_test',
            'enabled'  => true,
            'metadata' => ['percentage' => 50],
        ]);

        $this->assertIsArray($flag->fresh()->metadata);
    }
}
