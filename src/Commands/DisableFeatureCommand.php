<?php

namespace MugiWara\FeatureFlags\Commands;

use Illuminate\Console\Command;
use MugiWara\FeatureFlags\Contracts\FeatureManager;
use MugiWara\FeatureFlags\Drivers\DatabaseDriver;

class DisableFeatureCommand extends Command
{
    protected $signature = 'feature:disable
                            {name : The feature flag name}
                            {--tenant= : Scope the change to a specific tenant (database driver only)}';

    protected $description = 'Disable a feature flag';

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

        $manager->disable($name);

        $context = $tenant ? " (tenant: {$tenant})" : '';
        $this->components->info("Feature [{$name}]{$context} has been <fg=red>disabled</>.");

        if (! $driver instanceof DatabaseDriver) {
            $this->components->warn('Using config driver — this change is not persisted beyond the current process.');
        }

        return self::SUCCESS;
    }
}
