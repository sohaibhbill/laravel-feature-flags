<?php

namespace MugiWara\FeatureFlags\Commands;

use Illuminate\Console\Command;
use MugiWara\FeatureFlags\Contracts\FeatureManager;
use MugiWara\FeatureFlags\Drivers\DatabaseDriver;

class EnableFeatureCommand extends Command
{
    protected $signature = 'feature:enable
                            {name : The feature flag name}
                            {--tenant= : Scope the change to a specific tenant (database driver only)}';

    protected $description = 'Enable a feature flag';

    public function handle(FeatureManager $manager): int
    {
        $name   = $this->argument('name');
        $tenant = $this->option('tenant');
        $driver = $manager->getDriver();

        if ($tenant) {
            if (! $driver instanceof DatabaseDriver) {
                $this->components->error('The --tenant option is only supported with the database driver.');
                return self::FAILURE;
            }

            $driver->setTenant($tenant);
        }

        $manager->enable($name);

        $context = $tenant ? " (tenant: {$tenant})" : '';
        $this->components->info("Feature [{$name}]{$context} has been <fg=green>enabled</>.");

        if (! $driver instanceof DatabaseDriver) {
            $this->components->warn('Using config driver — this change is not persisted beyond the current process.');
        }

        return self::SUCCESS;
    }
}
