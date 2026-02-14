<?php

namespace MugiWara\FeatureFlags\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool isEnabled(string $feature)
 * @method static bool isDisabled(string $feature)
 * @method static void enable(string $feature)
 * @method static void disable(string $feature)
 * @method static mixed when(string $feature, callable $callback, ?callable $default = null)
 * @method static array all()
 * @method static bool forUser(\Illuminate\Contracts\Auth\Authenticatable $user, string $feature)
 * @method static void resolveUsing(?callable $resolver)
 *
 * @see \MugiWara\FeatureFlags\FeatureManager
 */
class Feature extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'feature';
    }
}