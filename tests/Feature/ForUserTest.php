<?php

namespace MugiWara\FeatureFlags\Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use MugiWara\FeatureFlags\Contracts\HasFeatureSegments;
use MugiWara\FeatureFlags\Facades\Feature;
use MugiWara\FeatureFlags\Tests\TestCase;

// ---------------------------------------------------------------------------
// Test doubles
// ---------------------------------------------------------------------------

/** A user that declares its segments via the interface. */
class SegmentedUser implements Authenticatable, HasFeatureSegments
{
    public function __construct(
        public readonly int $id,
        public readonly array $segments = []
    ) {}

    public function getFeatureSegments(): array
    {
        return $this->segments;
    }

    // Authenticatable boilerplate
    public function getAuthIdentifierName(): string  { return 'id'; }
    public function getAuthIdentifier(): mixed        { return $this->id; }
    public function getAuthPassword(): string         { return ''; }
    public function getRememberToken(): ?string       { return null; }
    public function setRememberToken($value): void    {}
    public function getRememberTokenName(): string    { return ''; }
    public function getAuthPasswordName(): string     { return 'password'; }
}

/** A user that does NOT implement HasFeatureSegments. */
class PlainUser implements Authenticatable
{
    public function __construct(public readonly int $id) {}

    public function getAuthIdentifierName(): string  { return 'id'; }
    public function getAuthIdentifier(): mixed        { return $this->id; }
    public function getAuthPassword(): string         { return ''; }
    public function getRememberToken(): ?string       { return null; }
    public function setRememberToken($value): void    {}
    public function getRememberTokenName(): string    { return ''; }
    public function getAuthPasswordName(): string     { return 'password'; }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class ForUserTest extends TestCase
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
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    private function insertFlag(string $name, bool $enabled, ?array $metadata = null): void
    {
        DB::table('feature_flags')->insert([
            'name'       => $name,
            'enabled'    => $enabled,
            'tenant_id'  => null,
            'metadata'   => $metadata ? json_encode($metadata) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // -----------------------------------------------------------------------
    // Base: flag disabled
    // -----------------------------------------------------------------------

    public function test_returns_false_when_flag_is_disabled(): void
    {
        $this->insertFlag('my_feature', false);
        $user = new SegmentedUser(1, ['beta_testers']);

        $this->assertFalse(Feature::forUser($user, 'my_feature'));
    }

    public function test_returns_false_when_flag_does_not_exist(): void
    {
        $user = new SegmentedUser(1);

        $this->assertFalse(Feature::forUser($user, 'unknown_feature'));
    }

    // -----------------------------------------------------------------------
    // No segments configured — any user can access
    // -----------------------------------------------------------------------

    public function test_returns_true_for_enabled_flag_with_no_segment_restriction(): void
    {
        $this->insertFlag('my_feature', true);
        $user = new SegmentedUser(1, []);

        $this->assertTrue(Feature::forUser($user, 'my_feature'));
    }

    public function test_plain_user_can_access_flag_with_no_segment_restriction(): void
    {
        $this->insertFlag('my_feature', true);
        $user = new PlainUser(1);

        $this->assertTrue(Feature::forUser($user, 'my_feature'));
    }

    // -----------------------------------------------------------------------
    // Segment filtering
    // -----------------------------------------------------------------------

    public function test_returns_true_when_user_segment_matches(): void
    {
        $this->insertFlag('beta_ui', true, ['segments' => ['beta_testers']]);
        $user = new SegmentedUser(1, ['beta_testers']);

        $this->assertTrue(Feature::forUser($user, 'beta_ui'));
    }

    public function test_returns_false_when_user_segment_does_not_match(): void
    {
        $this->insertFlag('beta_ui', true, ['segments' => ['beta_testers']]);
        $user = new SegmentedUser(1, ['regular_users']);

        $this->assertFalse(Feature::forUser($user, 'beta_ui'));
    }

    public function test_returns_false_for_plain_user_when_segment_restriction_exists(): void
    {
        // PlainUser has no getFeatureSegments() — treated as having no segments
        $this->insertFlag('beta_ui', true, ['segments' => ['beta_testers']]);
        $user = new PlainUser(1);

        $this->assertFalse(Feature::forUser($user, 'beta_ui'));
    }

    public function test_any_matching_segment_is_sufficient(): void
    {
        $this->insertFlag('pro_feature', true, ['segments' => ['staff', 'pro_plan']]);
        $user = new SegmentedUser(1, ['free_plan', 'pro_plan']);  // one of two matches

        $this->assertTrue(Feature::forUser($user, 'pro_feature'));
    }

    // -----------------------------------------------------------------------
    // Percentage rollout
    // -----------------------------------------------------------------------

    public function test_percentage_100_always_enables_for_user(): void
    {
        $this->insertFlag('rollout', true, ['percentage' => 100]);
        $user = new SegmentedUser(42);

        $this->assertTrue(Feature::forUser($user, 'rollout'));
    }

    public function test_percentage_0_always_disables_for_user(): void
    {
        $this->insertFlag('rollout', true, ['percentage' => 0]);
        $user = new SegmentedUser(42);

        // percentage=0 in PercentageRolloutStrategy always returns false
        $this->assertFalse(Feature::forUser($user, 'rollout'));
    }

    public function test_same_user_always_gets_same_result_for_percentage_rollout(): void
    {
        $this->insertFlag('rollout', true, ['percentage' => 50]);
        $user = new SegmentedUser(99);

        $first  = Feature::forUser($user, 'rollout');
        $second = Feature::forUser($user, 'rollout');

        $this->assertSame($first, $second);
    }

    public function test_different_users_can_get_different_results(): void
    {
        $this->insertFlag('rollout', true, ['percentage' => 50]);

        $results = array_map(
            fn($id) => Feature::forUser(new SegmentedUser($id), 'rollout'),
            range(1, 40)
        );

        $this->assertContains(true,  $results);
        $this->assertContains(false, $results);
    }

    // -----------------------------------------------------------------------
    // Segments + percentage combined
    // -----------------------------------------------------------------------

    public function test_user_outside_segment_is_rejected_before_percentage_check(): void
    {
        // Even though percentage=100 would allow anyone, the segment gate fires first
        $this->insertFlag('vip_feature', true, [
            'segments'   => ['vip'],
            'percentage' => 100,
        ]);
        $user = new SegmentedUser(1, ['regular']);

        $this->assertFalse(Feature::forUser($user, 'vip_feature'));
    }

    public function test_user_in_segment_still_subject_to_percentage_rollout(): void
    {
        $this->insertFlag('vip_feature', true, [
            'segments'   => ['vip'],
            'percentage' => 100,  // 100% within segment → always true
        ]);
        $user = new SegmentedUser(1, ['vip']);

        $this->assertTrue(Feature::forUser($user, 'vip_feature'));
    }

    // -----------------------------------------------------------------------
    // resolveUsing takes priority
    // -----------------------------------------------------------------------

    public function test_custom_resolver_overrides_all_user_logic(): void
    {
        $this->insertFlag('my_feature', false, ['segments' => ['vip']]);
        $user = new SegmentedUser(1, []);

        // Resolver says everything is enabled regardless
        Feature::resolveUsing(fn() => true);

        $this->assertTrue(Feature::forUser($user, 'my_feature'));

        Feature::resolveUsing(null); // clean up
    }

    // -----------------------------------------------------------------------
    // Config driver fallback
    // -----------------------------------------------------------------------

    public function test_config_driver_falls_back_to_is_enabled(): void
    {
        // Switch to config driver — no user-aware logic, just reads config
        config(['features.driver' => 'config', 'features.flags' => ['my_feature' => true]]);

        // Rebind the driver singleton for this test
        $this->app->forgetInstance(\MugiWara\FeatureFlags\Contracts\FeatureDriver::class);
        $this->app->forgetInstance(\MugiWara\FeatureFlags\Contracts\FeatureManager::class);
        $this->app->forgetInstance('feature');

        $user = new SegmentedUser(1, []);

        $this->assertTrue(Feature::forUser($user, 'my_feature'));
    }
}
