<?php

namespace MugiWara\FeatureFlags;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use MugiWara\FeatureFlags\Models\FeatureFlag;
use MugiWara\FeatureFlags\Observers\FeatureFlagObserver;
use MugiWara\FeatureFlags\Commands\CreateFeatureCommand;
use MugiWara\FeatureFlags\Commands\DisableFeatureCommand;
use MugiWara\FeatureFlags\Commands\EnableFeatureCommand;
use MugiWara\FeatureFlags\Commands\ListFeaturesCommand;
use MugiWara\FeatureFlags\Contracts\FeatureDriver;
use MugiWara\FeatureFlags\Contracts\FeatureManager as FeatureManagerContract;
use MugiWara\FeatureFlags\Drivers\ConfigDriver;
use MugiWara\FeatureFlags\Drivers\DatabaseDriver;

class FeatureFlagsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/features.php',
            'features'
        );

        // Bind the correct driver based on config
        $this->app->singleton(FeatureDriver::class, function ($app) {
            $config = $app['config']['features'];

            return match ($config['driver'] ?? 'config') {
                'database' => new DatabaseDriver($config),
                default    => new ConfigDriver($config),
            };
        });

        // Bind the FeatureManager contract to the concrete implementation
        $this->app->singleton(FeatureManagerContract::class, function ($app) {
            return new FeatureManager($app->make(FeatureDriver::class));
        });

        // Register the 'feature' alias used by the Feature facade
        $this->app->alias(FeatureManagerContract::class, 'feature');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish config
            $this->publishes([
                __DIR__ . '/../config/features.php' => config_path('features.php'),
            ], 'feature-flags-config');

            // Publish migration
            $this->publishes([
                __DIR__ . '/../database/migrations/' => database_path('migrations'),
            ], 'feature-flags-migrations');

            // Register Artisan commands
            $this->commands([
                ListFeaturesCommand::class,
                EnableFeatureCommand::class,
                DisableFeatureCommand::class,
                CreateFeatureCommand::class,
            ]);
        }

        // Register the route middleware alias
        $this->app['router']->aliasMiddleware(
            'feature',
            \MugiWara\FeatureFlags\Middleware\RequireFeature::class
        );

        FeatureFlag::observe(FeatureFlagObserver::class);

        $this->registerBladeDirectives();
    }

    private function registerBladeDirectives(): void
    {
        // @feature('flag_name') ... @endfeature
        Blade::directive('feature', function (string $expression): string {
            return "<?php if(app('feature')->isEnabled({$expression})): ?>";
        });

        // @elsefeature — optional else branch inside @feature ... @endfeature
        Blade::directive('elsefeature', function (): string {
            return '<?php else: ?>';
        });

        Blade::directive('endfeature', function (): string {
            return '<?php endif; ?>';
        });

        // @unlessfeature('flag_name') ... @endunlessfeature
        // Renders content only when the feature is DISABLED.
        Blade::directive('unlessfeature', function (string $expression): string {
            return "<?php if(app('feature')->isDisabled({$expression})): ?>";
        });

        Blade::directive('endunlessfeature', function (): string {
            return '<?php endif; ?>';
        });

        // @featureany(['flag1', 'flag2']) ... @endfeatureany
        // Renders content when ANY of the listed features is enabled.
        Blade::directive('featureany', function (string $expression): string {
            return "<?php if(app('feature')->someAreEnabled({$expression})): ?>";
        });

        Blade::directive('endfeatureany', function (): string {
            return '<?php endif; ?>';
        });
    }
}
