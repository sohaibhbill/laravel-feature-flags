<?php

namespace MugiWara\FeatureFlags\Concerns;

use MugiWara\FeatureFlags\Exceptions\FeatureDisabledException;
use MugiWara\FeatureFlags\Facades\Feature;

/**
 * Trait for protecting service methods with feature flags.
 */
trait FeatureGuarded
{
    /**
     * Execute a method only if the feature is enabled.
     *
     * @throws FeatureDisabledException
     */
    protected function requireFeature(string $feature): void
    {
        if (Feature::isDisabled($feature)) {
            throw new FeatureDisabledException("Feature '{$feature}' is disabled.");
        }
    }

    /**
     * Execute callback only if feature is enabled, return null otherwise.
     */
    protected function whenFeature(string $feature, callable $callback): mixed
    {
        return Feature::when($feature, $callback);
    }

    /**
     * Execute callback only if feature is enabled, throw exception otherwise.
     *
     * @throws FeatureDisabledException
     */
    protected function executeIfFeature(string $feature, callable $callback): mixed
    {
        $this->requireFeature($feature);
        return $callback();
    }
}