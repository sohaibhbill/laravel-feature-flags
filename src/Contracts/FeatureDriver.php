<?php

namespace MugiWara\FeatureFlags\Contracts;

interface FeatureDriver
{
    /**
     * Check if a feature is enabled.
     */
    public function isEnabled(string $feature): bool;

    /**
     * Enable a feature.
     */
    public function enable(string $feature): void;

    /**
     * Disable a feature.
     */
    public function disable(string $feature): void;
}