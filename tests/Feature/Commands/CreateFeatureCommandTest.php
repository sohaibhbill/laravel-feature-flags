<?php

namespace MugiWara\FeatureFlags\Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use MugiWara\FeatureFlags\Tests\TestCase;

class CreateFeatureCommandTest extends TestCase
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

    public function test_creates_disabled_flag_by_default(): void
    {
        $this->artisan('feature:create new_feature')
            ->assertSuccessful()
            ->expectsOutputToContain('new_feature')
            ->expectsOutputToContain('created successfully');

        $flag = DB::table('feature_flags')->where('name', 'new_feature')->first();
        $this->assertNotNull($flag);
        $this->assertFalse((bool) $flag->enabled);
    }

    public function test_creates_enabled_flag_with_enabled_option(): void
    {
        $this->artisan('feature:create new_feature --enabled')
            ->assertSuccessful();

        $this->assertTrue(
            (bool) DB::table('feature_flags')->where('name', 'new_feature')->value('enabled')
        );
    }

    public function test_creates_flag_with_description(): void
    {
        $this->artisan('feature:create new_feature --description="My feature"')
            ->assertSuccessful();

        $this->assertSame(
            'My feature',
            DB::table('feature_flags')->where('name', 'new_feature')->value('description')
        );
    }

    public function test_creates_flag_with_percentage_rollout(): void
    {
        $this->artisan('feature:create new_feature --percentage=25')
            ->assertSuccessful();

        $metadata = json_decode(
            DB::table('feature_flags')->where('name', 'new_feature')->value('metadata'),
            true
        );
        $this->assertSame(25, $metadata['percentage']);
    }

    public function test_creates_tenant_scoped_flag(): void
    {
        $this->artisan('feature:create new_feature --tenant=tenant_1')
            ->assertSuccessful();

        $this->assertDatabaseHas('feature_flags', [
            'name'      => 'new_feature',
            'tenant_id' => 'tenant_1',
        ]);
    }

    public function test_fails_on_duplicate_flag(): void
    {
        $this->artisan('feature:create new_feature')->assertSuccessful();

        $this->artisan('feature:create new_feature')
            ->assertFailed()
            ->expectsOutputToContain('already exists');
    }

    public function test_duplicate_check_is_scoped_to_tenant(): void
    {
        // Same name in different tenants should be allowed
        $this->artisan('feature:create new_feature --tenant=tenant_1')->assertSuccessful();
        $this->artisan('feature:create new_feature --tenant=tenant_2')->assertSuccessful();

        $this->assertSame(2, DB::table('feature_flags')->where('name', 'new_feature')->count());
    }

    public function test_fails_when_percentage_out_of_range(): void
    {
        $this->artisan('feature:create new_feature --percentage=0')
            ->assertFailed()
            ->expectsOutputToContain('percentage must be between 1 and 99');

        $this->artisan('feature:create new_feature --percentage=100')
            ->assertFailed()
            ->expectsOutputToContain('percentage must be between 1 and 99');
    }

    public function test_fails_with_non_database_driver(): void
    {
        config(['features.driver' => 'config']);

        $this->artisan('feature:create new_feature')
            ->assertFailed()
            ->expectsOutputToContain('database driver');
    }
}
