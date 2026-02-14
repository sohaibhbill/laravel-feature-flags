<?php

namespace MugiWara\FeatureFlags\Drivers;

use MugiWara\FeatureFlags\Contracts\FeatureDriver;

/**
 * Config-based driver. Reads feature flags from the 'flags' array
 * in config/features.php. Flags are read-only at runtime — enable()
 * and disable() have no effect (config cannot be persisted).
 */
class ConfigDriver implements FeatureDriver
{
    protected array $flags;

    public function __construct(array $config = [])
    {
        $this->flags = $config['flags'] ?? [];
    }

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

    /**
     * Return all known flags as ['name' => bool].
     */
    public function all(): array
    {
        return array_map(fn($v) => (bool) $v, $this->flags);
    }
}
