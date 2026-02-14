<?php

namespace MugiWara\FeatureFlags\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use MugiWara\FeatureFlags\Contracts\FeatureManager;
use MugiWara\FeatureFlags\Drivers\DatabaseDriver;

class CreateFeatureCommand extends Command
{
    protected $signature = 'feature:create
                            {name : The feature flag name}
                            {--enabled : Create the flag in an enabled state (default: disabled)}
                            {--description= : Human-readable description}
                            {--percentage= : Enable for this percentage of users (1–99)}
                            {--tenant= : Scope the flag to a specific tenant}';

    protected $description = 'Create a new feature flag in the database';

    public function handle(FeatureManager $manager): int
    {
        if (! $manager->getDriver() instanceof DatabaseDriver) {
            $this->components->error(
                'feature:create requires the database driver. ' .
                'Set FEATURE_FLAGS_DRIVER=database in your .env and run migrations.'
            );
            return self::FAILURE;
        }

        $name        = $this->argument('name');
        $tenant      = $this->option('tenant') ?: null;
        $enabled     = (bool) $this->option('enabled');
        $description = $this->option('description') ?: null;
        $percentage  = $this->option('percentage');

        // Validate percentage
        if ($percentage !== null) {
            $percentage = (int) $percentage;
            if ($percentage < 1 || $percentage > 99) {
                $this->components->error('--percentage must be between 1 and 99.');
                return self::FAILURE;
            }
        }

        // Check for duplicate
        $exists = DB::table('feature_flags')
            ->where('name', $name)
            ->where(fn($q) => $tenant
                ? $q->where('tenant_id', $tenant)
                : $q->whereNull('tenant_id')
            )
            ->exists();

        if ($exists) {
            $scope = $tenant ? "tenant [{$tenant}]" : 'global scope';
            $this->components->error("Feature [{$name}] already exists in {$scope}.");
            return self::FAILURE;
        }

        $metadata = $percentage !== null ? ['percentage' => $percentage] : null;

        DB::table('feature_flags')->insert([
            'name'        => $name,
            'enabled'     => $enabled,
            'tenant_id'   => $tenant,
            'description' => $description,
            'metadata'    => $metadata !== null ? json_encode($metadata) : null,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->components->info("Feature [{$name}] created successfully.");

        $this->table(
            ['Name', 'Status', 'Tenant', 'Rollout %', 'Description'],
            [[
                $name,
                $enabled ? '<fg=green>enabled</>' : '<fg=red>disabled</>',
                $tenant ?? '<fg=gray>global</>',
                $percentage !== null ? "{$percentage}%" : '<fg=gray>—</>',
                $description ?? '<fg=gray>—</>',
            ]]
        );

        return self::SUCCESS;
    }
}
