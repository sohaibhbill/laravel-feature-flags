<?php

namespace MugiWara\FeatureFlags\Tests\Unit;

use MugiWara\FeatureFlags\Contracts\FeatureDriver;
use MugiWara\FeatureFlags\FeatureManager;
use MugiWara\FeatureFlags\Tests\TestCase;

class FeatureManagerTest extends TestCase
{
    private function managerWith(array $flags): FeatureManager
    {
        $driver = new class($flags) implements FeatureDriver {
            public function __construct(private array $flags) {}

            public function isEnabled(string $feature): bool
            {
                return (bool) ($this->flags[$feature] ?? false);
            }

            public function enable(string $feature): void
            {
                $this->flags[$feature] = true;
            }

            public function disable(string $feature): void
            {
                $this->flags[$feature] = false;
            }
        };

        return new FeatureManager($driver);
    }

    public function test_is_enabled_delegates_to_driver(): void
    {
        $manager = $this->managerWith(['feature_a' => true]);

        $this->assertTrue($manager->isEnabled('feature_a'));
    }

    public function test_is_disabled_is_inverse_of_is_enabled(): void
    {
        $manager = $this->managerWith(['feature_a' => true, 'feature_b' => false]);

        $this->assertFalse($manager->isDisabled('feature_a'));
        $this->assertTrue($manager->isDisabled('feature_b'));
    }

    public function test_enable_delegates_to_driver(): void
    {
        $manager = $this->managerWith(['feature_a' => false]);

        $manager->enable('feature_a');

        $this->assertTrue($manager->isEnabled('feature_a'));
    }

    public function test_disable_delegates_to_driver(): void
    {
        $manager = $this->managerWith(['feature_a' => true]);

        $manager->disable('feature_a');

        $this->assertFalse($manager->isEnabled('feature_a'));
    }

    public function test_when_executes_callback_if_enabled(): void
    {
        $manager = $this->managerWith(['feature_a' => true]);
        $called = false;

        $manager->when('feature_a', function () use (&$called) {
            $called = true;
        });

        $this->assertTrue($called);
    }

    public function test_when_does_not_execute_callback_if_disabled(): void
    {
        $manager = $this->managerWith(['feature_a' => false]);
        $called = false;

        $manager->when('feature_a', function () use (&$called) {
            $called = true;
        });

        $this->assertFalse($called);
    }

    public function test_when_returns_callback_return_value(): void
    {
        $manager = $this->managerWith(['feature_a' => true]);

        $result = $manager->when('feature_a', fn() => 'premium');

        $this->assertSame('premium', $result);
    }

    public function test_when_returns_null_if_disabled_and_no_default(): void
    {
        $manager = $this->managerWith(['feature_a' => false]);

        $result = $manager->when('feature_a', fn() => 'premium');

        $this->assertNull($result);
    }

    public function test_when_executes_default_callback_if_disabled(): void
    {
        $manager = $this->managerWith(['feature_a' => false]);

        $result = $manager->when('feature_a', fn() => 'premium', fn() => 'free');

        $this->assertSame('free', $result);
    }

    public function test_resolve_using_overrides_driver(): void
    {
        // Driver says disabled, but resolver overrides to enabled
        $manager = $this->managerWith(['feature_a' => false]);

        $manager->resolveUsing(fn(string $feature) => true);

        $this->assertTrue($manager->isEnabled('feature_a'));
    }

    public function test_resolve_using_null_removes_resolver(): void
    {
        $manager = $this->managerWith(['feature_a' => false]);
        $manager->resolveUsing(fn() => true);

        $manager->resolveUsing(null);

        // Should fall back to the driver (which says false)
        $this->assertFalse($manager->isEnabled('feature_a'));
    }

    public function test_resolve_using_receives_feature_name(): void
    {
        $manager = $this->managerWith([]);
        $received = null;

        $manager->resolveUsing(function (string $feature) use (&$received) {
            $received = $feature;
            return false;
        });

        $manager->isEnabled('my_feature');

        $this->assertSame('my_feature', $received);
    }

    public function test_get_driver_returns_driver_instance(): void
    {
        $manager = $this->managerWith([]);

        $this->assertInstanceOf(FeatureDriver::class, $manager->getDriver());
    }
}
