<?php

namespace MugiWara\FeatureFlags\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use MugiWara\FeatureFlags\Events\FeatureDisabled;
use MugiWara\FeatureFlags\Events\FeatureEnabled;
use MugiWara\FeatureFlags\Facades\Feature;
use MugiWara\FeatureFlags\Tests\TestCase;

class EventsTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('features.driver', 'database');
        $app['config']->set('features.cache.enabled', false);
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
    // FeatureEnabled
    // -----------------------------------------------------------------------

    public function test_feature_enabled_event_is_dispatched_on_enable(): void
    {
        Event::fake([FeatureEnabled::class]);

        Feature::enable('beta_ui');

        Event::assertDispatched(FeatureEnabled::class, function (FeatureEnabled $event) {
            return $event->feature === 'beta_ui' && $event->tenantId === null;
        });
    }

    public function test_feature_enabled_event_carries_tenant_id(): void
    {
        Event::fake([FeatureEnabled::class]);

        Feature::getDriver()->setTenant('tenant_1');
        Feature::enable('beta_ui');
        Feature::getDriver()->setTenant(null); // reset

        Event::assertDispatched(FeatureEnabled::class, function (FeatureEnabled $event) {
            return $event->feature === 'beta_ui' && $event->tenantId === 'tenant_1';
        });
    }

    public function test_feature_enabled_event_is_not_dispatched_on_disable(): void
    {
        Event::fake([FeatureEnabled::class]);

        Feature::disable('beta_ui');

        Event::assertNotDispatched(FeatureEnabled::class);
    }

    // -----------------------------------------------------------------------
    // FeatureDisabled
    // -----------------------------------------------------------------------

    public function test_feature_disabled_event_is_dispatched_on_disable(): void
    {
        Event::fake([FeatureDisabled::class]);

        Feature::disable('beta_ui');

        Event::assertDispatched(FeatureDisabled::class, function (FeatureDisabled $event) {
            return $event->feature === 'beta_ui' && $event->tenantId === null;
        });
    }

    public function test_feature_disabled_event_carries_tenant_id(): void
    {
        Event::fake([FeatureDisabled::class]);

        Feature::getDriver()->setTenant('tenant_2');
        Feature::disable('beta_ui');
        Feature::getDriver()->setTenant(null); // reset

        Event::assertDispatched(FeatureDisabled::class, function (FeatureDisabled $event) {
            return $event->feature === 'beta_ui' && $event->tenantId === 'tenant_2';
        });
    }

    public function test_feature_disabled_event_is_not_dispatched_on_enable(): void
    {
        Event::fake([FeatureDisabled::class]);

        Feature::enable('beta_ui');

        Event::assertNotDispatched(FeatureDisabled::class);
    }

    // -----------------------------------------------------------------------
    // Config driver — events fire but tenantId is always null
    // -----------------------------------------------------------------------

    public function test_events_fire_with_config_driver_without_tenant(): void
    {
        config(['features.driver' => 'config', 'features.flags' => ['my_flag' => false]]);
        $this->app->forgetInstance(\MugiWara\FeatureFlags\Contracts\FeatureDriver::class);
        $this->app->forgetInstance(\MugiWara\FeatureFlags\Contracts\FeatureManager::class);
        $this->app->forgetInstance('feature');

        Event::fake([FeatureEnabled::class]);

        Feature::enable('my_flag');

        Event::assertDispatched(FeatureEnabled::class, function (FeatureEnabled $event) {
            return $event->feature === 'my_flag' && $event->tenantId === null;
        });
    }

    // -----------------------------------------------------------------------
    // Event properties are correct types
    // -----------------------------------------------------------------------

    public function test_feature_enabled_event_properties(): void
    {
        $event = new FeatureEnabled('beta_ui', 'tenant_1');

        $this->assertSame('beta_ui', $event->feature);
        $this->assertSame('tenant_1', $event->tenantId);
    }

    public function test_feature_disabled_event_properties(): void
    {
        $event = new FeatureDisabled('beta_ui');

        $this->assertSame('beta_ui', $event->feature);
        $this->assertNull($event->tenantId);
    }

    // -----------------------------------------------------------------------
    // Listener integration — verify apps can react
    // -----------------------------------------------------------------------

    public function test_listener_receives_feature_enabled_event(): void
    {
        $received = null;

        $this->app['events']->listen(FeatureEnabled::class, function (FeatureEnabled $event) use (&$received) {
            $received = $event->feature;
        });

        Feature::enable('new_feature');

        $this->assertSame('new_feature', $received);
    }

    public function test_listener_receives_feature_disabled_event(): void
    {
        $received = null;

        $this->app['events']->listen(FeatureDisabled::class, function (FeatureDisabled $event) use (&$received) {
            $received = $event->feature;
        });

        Feature::disable('new_feature');

        $this->assertSame('new_feature', $received);
    }
}
