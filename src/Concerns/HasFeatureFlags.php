<?php

namespace MugiWara\FeatureFlags\Concerns;

use Illuminate\Database\Eloquent\Builder;
use MugiWara\FeatureFlags\Facades\Feature;

trait HasFeatureFlags
{
    /**
     * Get the feature flag associated with this model.
     */
    abstract public function getFeatureFlag(): string;

    /**
     * Boot the trait.
     */
    public static function bootHasFeatureFlags(): void
    {
        static::addGlobalScope('feature_flag', function (Builder $builder) {
            $instance = new static;
            $feature = $instance->getFeatureFlag();

            if (Feature::isDisabled($feature)) {
                $builder->whereRaw('1 = 0');
            }
        });
    }

    /**
     * Check if this model's feature is enabled.
     */
    public function featureIsEnabled(): bool
    {
        return Feature::isEnabled($this->getFeatureFlag());
    }

    /**
     * Check if this model's feature is disabled.
     */
    public function featureIsDisabled(): bool
    {
        return Feature::isDisabled($this->getFeatureFlag());
    }

    /**
     * Execute callback only if feature is enabled.
     */
    public function whenFeatureEnabled(callable $callback, ?callable $default = null): mixed
    {
        return Feature::when($this->getFeatureFlag(), $callback, $default);
    }
}