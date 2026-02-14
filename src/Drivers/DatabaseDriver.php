<?php

namespace MugiWara\FeatureFlags\Drivers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use MugiWara\FeatureFlags\Contracts\FeatureDriver;
use MugiWara\FeatureFlags\Contracts\HasFeatureSegments;
use MugiWara\FeatureFlags\Strategies\PercentageRolloutStrategy;

/**
 * Database driver for dynamic feature flag management.
 * Supports caching and per-tenant flags.
 */
class DatabaseDriver implements FeatureDriver
{
    protected array $config;
    protected ?string $tenantId = null;
    protected string|int|null $identifier = null;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function isEnabled(string $feature): bool
    {
        $cacheKey = $this->getCacheKey($feature);

        if ($this->shouldCache()) {
            return Cache::remember($cacheKey, $this->getCacheTtl(), function () use ($feature) {
                return $this->fetchFromDatabase($feature);
            });
        }

        return $this->fetchFromDatabase($feature);
    }

    public function enable(string $feature): void
    {
        DB::table('feature_flags')->updateOrInsert(
            [
                'name' => $feature,
                'tenant_id' => $this->tenantId,
            ],
            [
                'enabled' => true,
                'updated_at' => now(),
            ]
        );

        $this->clearCache($feature);
    }

    public function disable(string $feature): void
    {
        DB::table('feature_flags')->updateOrInsert(
            [
                'name' => $feature,
                'tenant_id' => $this->tenantId,
            ],
            [
                'enabled' => false,
                'updated_at' => now(),
            ]
        );

        $this->clearCache($feature);
    }

    public function setTenant(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    /**
     * Set the current user identifier used for percentage rollout decisions.
     * Typically auth()->id() or any unique string per user.
     */
    public function setIdentifier(string|int|null $identifier): void
    {
        $this->identifier = $identifier;
    }

    /**
     * Return all flags as ['name' => bool], scoped to the current tenant
     * (or global-only when no tenant is set).
     */
    public function all(): array
    {
        $query = DB::table('feature_flags');

        if ($this->tenantId !== null) {
            $query->where(function ($q) {
                $q->where('tenant_id', $this->tenantId)
                    ->orWhereNull('tenant_id');
            });
        } else {
            $query->whereNull('tenant_id');
        }

        return $query->get()
            ->pluck('enabled', 'name')
            ->map(fn($v) => (bool) $v)
            ->all();
    }

    /**
     * Full user-aware check: enabled → segment restriction → percentage rollout.
     *
     * Skips the cache intentionally — per-user results vary and caching them
     * would require one cache entry per user per feature, which is impractical
     * at scale. Use resolveUsing() if you need a custom caching strategy.
     */
    public function checkForUser(string $feature, Authenticatable $user): bool
    {
        $flag = $this->fetchFlagRow($feature);

        if (! $flag || ! $flag->enabled) {
            return false;
        }

        $metadata = is_string($flag->metadata)
            ? json_decode($flag->metadata, true)
            : (array) ($flag->metadata ?? []);

        // Segment check — if the flag restricts to specific segments, the user
        // must belong to at least one of them.
        $allowedSegments = $metadata['segments'] ?? [];

        if (! empty($allowedSegments)) {
            $userSegments = $user instanceof HasFeatureSegments
                ? $user->getFeatureSegments()
                : [];

            if (empty(array_intersect($allowedSegments, $userSegments))) {
                return false;
            }
        }

        // Percentage rollout — consistent per user+feature combination.
        if (! empty($metadata['percentage'])) {
            return (new PercentageRolloutStrategy())
                ->shouldEnable($flag->name, $user->getAuthIdentifier(), (int) $metadata['percentage']);
        }

        return true;
    }

    /**
     * Shared query used by both fetchFromDatabase() and checkForUser().
     */
    protected function fetchFlagRow(string $feature): ?object
    {
        $query = DB::table('feature_flags')->where('name', $feature);

        if ($this->tenantId !== null) {
            $query->where(function ($q) {
                $q->where('tenant_id', $this->tenantId)
                    ->orWhereNull('tenant_id');
            });
        } else {
            $query->whereNull('tenant_id');
        }

        return $query->orderByDesc('tenant_id')->first();
    }

    protected function fetchFromDatabase(string $feature): bool
    {
        $flag = $this->fetchFlagRow($feature);

        if (! $flag || ! $flag->enabled) {
            return false;
        }

        // Apply percentage rollout when metadata carries a 'percentage' key.
        // metadata arrives as a raw JSON string from the DB query builder
        // (unlike Eloquent, which would cast it), so we decode it manually.
        $metadata = is_string($flag->metadata)
            ? json_decode($flag->metadata, true)
            : (array) ($flag->metadata ?? []);

        if (! empty($metadata['percentage']) && $this->identifier !== null) {
            return (new PercentageRolloutStrategy())
                ->shouldEnable($flag->name, $this->identifier, (int) $metadata['percentage']);
        }

        return true;
    }

    protected function shouldCache(): bool
    {
        return $this->config['cache']['enabled'] ?? true;
    }

    protected function getCacheTtl(): int
    {
        return $this->config['cache']['ttl'] ?? 3600;
    }

    protected function getCacheKey(string $feature): string
    {
        $prefix = $this->config['cache']['prefix'] ?? 'feature_flag:';
        $tenant = $this->tenantId ? ":{$this->tenantId}" : '';
        return "{$prefix}{$feature}{$tenant}";
    }

    protected function clearCache(string $feature): void
    {
        if ($this->shouldCache()) {
            Cache::forget($this->getCacheKey($feature));
        }
    }
}