<?php

namespace MugiWara\FeatureFlags;

use Illuminate\Contracts\Auth\Authenticatable;
use MugiWara\FeatureFlags\Contracts\FeatureDriver;
use MugiWara\FeatureFlags\Contracts\FeatureManager as FeatureManagerContract;
use MugiWara\FeatureFlags\Events\FeatureDisabled;
use MugiWara\FeatureFlags\Events\FeatureEnabled;

class FeatureManager implements FeatureManagerContract
{
    /**
     * A custom resolver that, when set, takes priority over the driver.
     * Signature: fn(string $feature): bool
     */
    protected ?\Closure $resolver = null;

    public function __construct(protected FeatureDriver $driver) {}

    public function isEnabled(string $feature): bool
    {
        if ($this->resolver !== null) {
            return (bool) ($this->resolver)($feature);
        }

        return $this->driver->isEnabled($feature);
    }

    public function isDisabled(string $feature): bool
    {
        return ! $this->isEnabled($feature);
    }

    public function enable(string $feature): void
    {
        $this->driver->enable($feature);

        FeatureEnabled::dispatch($feature, $this->resolveTenantId());
    }

    public function disable(string $feature): void
    {
        $this->driver->disable($feature);

        FeatureDisabled::dispatch($feature, $this->resolveTenantId());
    }

    public function when(string $feature, callable $callback, ?callable $default = null): mixed
    {
        if ($this->isEnabled($feature)) {
            return $callback();
        }

        return $default ? $default() : null;
    }

    public function all(): array
    {
        // The driver knows which flags exist; delegate if it supports it,
        // otherwise return an empty array (drivers can override this via
        // the FeatureDriver contract extension in a future iteration).
        if (method_exists($this->driver, 'all')) {
            return $this->driver->all();
        }

        return [];
    }

    public function forUser(Authenticatable $user, string $feature): bool
    {
        // A custom resolver always takes priority — it owns the full decision.
        if ($this->resolver !== null) {
            return (bool) ($this->resolver)($feature);
        }

        // Delegate to the driver's user-aware check when available
        // (DatabaseDriver implements this; custom drivers may too).
        if (method_exists($this->driver, 'checkForUser')) {
            return $this->driver->checkForUser($feature, $user);
        }

        // Config driver (and any driver without checkForUser) falls back to
        // the standard enabled check — no per-user logic is possible.
        return $this->driver->isEnabled($feature);
    }

    public function someAreEnabled(array $features): bool
    {
        foreach ($features as $feature) {
            if ($this->isEnabled($feature)) {
                return true;
            }
        }

        return false;
    }

    public function resolveUsing(?callable $resolver): void
    {
        $this->resolver = $resolver;
    }

    /**
     * Expose the underlying driver so callers (e.g. tenant middleware)
     * can access driver-specific methods like setTenant().
     */
    public function getDriver(): FeatureDriver
    {
        return $this->driver;
    }

    /**
     * Pull the current tenant ID from the driver when it supports it,
     * so events carry the full context without the manager needing to
     * know about tenancy directly.
     */
    private function resolveTenantId(): ?string
    {
        if (method_exists($this->driver, 'getTenantId')) {
            return $this->driver->getTenantId();
        }

        return null;
    }
}
