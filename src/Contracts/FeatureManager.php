<?php

namespace MugiWara\FeatureFlags\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface FeatureManager
{
    /**
     * Check if a feature is enabled.
     */
    public function isEnabled(string $feature): bool;

    /**
     * Check if a feature is disabled.
     */
    public function isDisabled(string $feature): bool;

    /**
     * Enable a feature at runtime.
     */
    public function enable(string $feature): void;

    /**
     * Disable a feature at runtime.
     */
    public function disable(string $feature): void;

    /**
     * Execute callback only if feature is enabled.
     */
    public function when(string $feature, callable $callback, ?callable $default = null): mixed;

    /**
     * Get all features with their status.
     */
    public function all(): array;

    /**
     * Check if a feature is enabled for a specific user, applying
     * segment restrictions and percentage rollout where configured.
     */
    public function forUser(Authenticatable $user, string $feature): bool;

    /**
     * Check if any of the given features are enabled.
     */
    public function someAreEnabled(array $features): bool;

    /**
     * Set a custom resolver for dynamic feature checking.
     */
    public function resolveUsing(?callable $resolver): void;
}