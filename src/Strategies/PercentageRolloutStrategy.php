<?php

namespace MugiWara\FeatureFlags\Strategies;

/**
 * Strategy for gradual feature rollout to a percentage of users.
 * Uses consistent hashing to ensure same user always gets same result.
 */
class PercentageRolloutStrategy
{
    /**
     * Determine if feature should be enabled for a given user.
     */
    public function shouldEnable(string $feature, string|int $identifier, int $percentage): bool
    {
        if ($percentage <= 0) {
            return false;
        }

        if ($percentage >= 100) {
            return true;
        }

        // Create consistent hash for this feature + user combination.
        // abs() is required because crc32() returns signed integers on 64-bit
        // PHP; without it ~50% of hashes are negative and % 100 yields a
        // negative bucket, making the comparison always true for those users.
        $bucket = $this->getBucket($feature, $identifier);

        return $bucket < $percentage;
    }

    /**
     * Get the bucket number (0-99) for a feature + identifier combination.
     */
    public function getBucket(string $feature, string|int $identifier): int
    {
        return abs(crc32($feature . ':' . (string) $identifier)) % 100;
    }
}

// Usage example:
// $strategy = new PercentageRolloutStrategy();
// if ($strategy->shouldEnable('new_ui', auth()->id(), 25)) {
//     // Show new UI to 25% of users
// }