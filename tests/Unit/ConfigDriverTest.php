<?php

namespace MugiWara\FeatureFlags\Tests\Unit;

use MugiWara\FeatureFlags\Drivers\ConfigDriver;
use MugiWara\FeatureFlags\Tests\TestCase;

class ConfigDriverTest extends TestCase
{
    private function driver(array $flags = []): ConfigDriver
    {
        return new ConfigDriver(['flags' => $flags]);
    }

    public function test_returns_true_for_enabled_flag(): void
    {
        $driver = $this->driver(['my_feature' => true]);

        $this->assertTrue($driver->isEnabled('my_feature'));
    }

    public function test_returns_false_for_disabled_flag(): void
    {
        $driver = $this->driver(['my_feature' => false]);

        $this->assertFalse($driver->isEnabled('my_feature'));
    }

    public function test_returns_false_for_unknown_flag(): void
    {
        $driver = $this->driver([]);

        $this->assertFalse($driver->isEnabled('does_not_exist'));
    }

    public function test_enable_makes_flag_return_true(): void
    {
        $driver = $this->driver(['my_feature' => false]);

        $driver->enable('my_feature');

        $this->assertTrue($driver->isEnabled('my_feature'));
    }

    public function test_disable_makes_flag_return_false(): void
    {
        $driver = $this->driver(['my_feature' => true]);

        $driver->disable('my_feature');

        $this->assertFalse($driver->isEnabled('my_feature'));
    }

    public function test_enable_works_on_unknown_flag(): void
    {
        $driver = $this->driver([]);

        $driver->enable('brand_new');

        $this->assertTrue($driver->isEnabled('brand_new'));
    }

    public function test_truthy_values_count_as_enabled(): void
    {
        // env() returns a string '1' for true — must still work
        $driver = $this->driver(['my_feature' => '1']);

        $this->assertTrue($driver->isEnabled('my_feature'));
    }

    public function test_falsy_values_count_as_disabled(): void
    {
        $driver = $this->driver(['my_feature' => '0']);

        $this->assertFalse($driver->isEnabled('my_feature'));
    }
}
