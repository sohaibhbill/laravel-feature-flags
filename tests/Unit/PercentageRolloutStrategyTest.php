<?php

namespace MugiWara\FeatureFlags\Tests\Unit;

use MugiWara\FeatureFlags\Strategies\PercentageRolloutStrategy;
use MugiWara\FeatureFlags\Tests\TestCase;

class PercentageRolloutStrategyTest extends TestCase
{
    private PercentageRolloutStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new PercentageRolloutStrategy();
    }

    public function test_zero_percent_always_disabled(): void
    {
        $this->assertFalse($this->strategy->shouldEnable('feature', 'user_1', 0));
        $this->assertFalse($this->strategy->shouldEnable('feature', 'user_2', 0));
    }

    public function test_negative_percent_always_disabled(): void
    {
        $this->assertFalse($this->strategy->shouldEnable('feature', 'user_1', -1));
    }

    public function test_hundred_percent_always_enabled(): void
    {
        $this->assertTrue($this->strategy->shouldEnable('feature', 'user_1', 100));
        $this->assertTrue($this->strategy->shouldEnable('feature', 'user_2', 100));
    }

    public function test_over_hundred_percent_always_enabled(): void
    {
        $this->assertTrue($this->strategy->shouldEnable('feature', 'user_1', 101));
    }

    public function test_same_user_and_feature_always_yields_same_result(): void
    {
        $first  = $this->strategy->shouldEnable('beta_ui', 42, 50);
        $second = $this->strategy->shouldEnable('beta_ui', 42, 50);

        $this->assertSame($first, $second);
    }

    public function test_different_features_can_yield_different_results_for_same_user(): void
    {
        // Run over many feature names to confirm at least one differs
        $results = array_map(
            fn($i) => $this->strategy->shouldEnable("feature_{$i}", 999, 50),
            range(1, 20)
        );

        // Not all results should be identical (they could be, but overwhelmingly won't)
        $this->assertContains(true,  $results);
        $this->assertContains(false, $results);
    }

    public function test_approximately_correct_distribution_at_50_percent(): void
    {
        $enabled = 0;
        $total   = 1000;

        for ($i = 0; $i < $total; $i++) {
            if ($this->strategy->shouldEnable('rollout', "user_{$i}", 50)) {
                $enabled++;
            }
        }

        // Expect roughly 50 ± 10%
        $this->assertGreaterThan(400, $enabled);
        $this->assertLessThan(600, $enabled);
    }

    public function test_approximately_correct_distribution_at_25_percent(): void
    {
        $enabled = 0;
        $total   = 1000;

        for ($i = 0; $i < $total; $i++) {
            if ($this->strategy->shouldEnable('rollout', "user_{$i}", 25)) {
                $enabled++;
            }
        }

        // Expect roughly 25 ± 10%
        $this->assertGreaterThan(150, $enabled);
        $this->assertLessThan(350, $enabled);
    }

    public function test_get_bucket_returns_value_between_0_and_99(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $bucket = $this->strategy->getBucket('feature', "user_{$i}");
            $this->assertGreaterThanOrEqual(0, $bucket);
            $this->assertLessThanOrEqual(99, $bucket);
        }
    }

    public function test_get_bucket_is_consistent_for_same_inputs(): void
    {
        $a = $this->strategy->getBucket('beta_ui', 'user_42');
        $b = $this->strategy->getBucket('beta_ui', 'user_42');

        $this->assertSame($a, $b);
    }

    public function test_string_and_int_identifiers_produce_same_bucket(): void
    {
        // Both should hash to the same string "feature:42"
        $fromInt    = $this->strategy->getBucket('feature', 42);
        $fromString = $this->strategy->getBucket('feature', '42');

        $this->assertSame($fromInt, $fromString);
    }
}
