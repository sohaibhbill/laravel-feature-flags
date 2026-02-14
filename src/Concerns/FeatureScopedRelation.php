<?php

namespace MugiWara\FeatureFlags\Concerns;

use Illuminate\Database\Eloquent\Relations\Relation;
use MugiWara\FeatureFlags\Facades\Feature;

trait FeatureScopedRelation
{
    /**
     * Define a feature-protected relationship.
     *
     * Always returns a Relation — when the feature is disabled the relation
     * is constrained with whereRaw('1 = 0') so it yields empty results.
     * This is safe to chain (->get(), ->first(), dynamic property access, etc.)
     * without null-checks on the caller side.
     */
    protected function featureRelation(string $feature, callable $relationCallback): Relation
    {
        $relation = $relationCallback();

        if (Feature::isDisabled($feature)) {
            return $relation->whereRaw('1 = 0');
        }

        return $relation;
    }

    /**
     * Define a hasMany relationship protected by a feature flag.
     */
    public function hasManyWithFeature(string $feature, string $related, ?string $foreignKey = null, ?string $localKey = null)
    {
        if (Feature::isDisabled($feature)) {
            return $this->hasMany($related, $foreignKey, $localKey)->whereRaw('1 = 0');
        }

        return $this->hasMany($related, $foreignKey, $localKey);
    }

    /**
     * Define a belongsTo relationship protected by a feature flag.
     */
    public function belongsToWithFeature(string $feature, string $related, ?string $foreignKey = null, ?string $ownerKey = null, ?string $relation = null)
    {
        if (Feature::isDisabled($feature)) {
            return $this->belongsTo($related, $foreignKey, $ownerKey, $relation)->whereRaw('1 = 0');
        }

        return $this->belongsTo($related, $foreignKey, $ownerKey, $relation);
    }

    /**
     * Define a hasOne relationship protected by a feature flag.
     */
    public function hasOneWithFeature(string $feature, string $related, ?string $foreignKey = null, ?string $localKey = null)
    {
        if (Feature::isDisabled($feature)) {
            return $this->hasOne($related, $foreignKey, $localKey)->whereRaw('1 = 0');
        }

        return $this->hasOne($related, $foreignKey, $localKey);
    }

    /**
     * Define a belongsToMany relationship protected by a feature flag.
     */
    public function belongsToManyWithFeature(
        string $feature,
        string $related,
        ?string $table = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $parentKey = null,
        ?string $relatedKey = null,
        ?string $relation = null
    ) {
        if (Feature::isDisabled($feature)) {
            return $this->belongsToMany($related, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relation)
                ->whereRaw('1 = 0');
        }

        return $this->belongsToMany($related, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relation);
    }
}