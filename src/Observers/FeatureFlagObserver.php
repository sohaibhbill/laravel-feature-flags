<?php

namespace MugiWara\FeatureFlags\Observers;

use Illuminate\Support\Facades\Cache;
use MugiWara\FeatureFlags\Models\FeatureFlag;

/**
 * Clears the feature flag cache whenever a FeatureFlag record is
 * saved or deleted — regardless of whether the change came through
 * the driver (enable/disable), a seeder, Tinker, or a migration.
 *
 * The cache key format mirrors DatabaseDriver::getCacheKey() exactly:
 *   {prefix}{name}           — for global flags (tenant_id IS NULL)
 *   {prefix}{name}:{tenant}  — for tenant-scoped flags
 */
class FeatureFlagObserver
{
    public function saved(FeatureFlag $flag): void
    {
        $this->clearCache($flag);
    }

    public function deleted(FeatureFlag $flag): void
    {
        $this->clearCache($flag);
    }

    private function clearCache(FeatureFlag $flag): void
    {
        if (! config('features.cache.enabled', true)) {
            return;
        }

        Cache::forget($this->cacheKey($flag));
    }

    private function cacheKey(FeatureFlag $flag): string
    {
        $prefix = config('features.cache.prefix', 'feature_flag:');
        $tenant = $flag->tenant_id ? ":{$flag->tenant_id}" : '';

        return "{$prefix}{$flag->name}{$tenant}";
    }
}
