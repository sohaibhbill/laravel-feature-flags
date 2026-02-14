<?php

namespace MugiWara\FeatureFlags\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * Feature Flag Model
 *
 * @property string $name
 * @property bool $enabled
 * @property string|null $tenant_id
 * @property string|null $description
 * @property array|null $metadata
 */
class FeatureFlag extends Model
{
    protected $fillable = [
        'name',
        'enabled',
        'tenant_id',
        'description',
        'metadata',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Scope to get global features (not tenant-specific).
     */
    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('tenant_id');
    }

    /**
     * Scope to get features for a specific tenant.
     */
    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope to get only enabled features.
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope to get only disabled features.
     */
    public function scopeDisabled(Builder $query): Builder
    {
        return $query->where('enabled', false);
    }

    /**
     * Check if this feature has percentage rollout configured.
     */
    public function hasPercentageRollout(): bool
    {
        return isset($this->metadata['percentage']) &&
            $this->metadata['percentage'] > 0 &&
            $this->metadata['percentage'] < 100;
    }

    /**
     * Get the percentage for gradual rollout.
     */
    public function getRolloutPercentage(): ?int
    {
        return $this->metadata['percentage'] ?? null;
    }

    /**
     * Get allowed user segments.
     */
    public function getAllowedSegments(): array
    {
        return $this->metadata['segments'] ?? [];
    }

    /**
     * Check if feature is available for a specific segment.
     */
    public function isAvailableForSegment(string $segment): bool
    {
        $segments = $this->getAllowedSegments();
        return empty($segments) || in_array($segment, $segments);
    }
}