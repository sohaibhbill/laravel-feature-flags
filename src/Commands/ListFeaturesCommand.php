<?php

namespace MugiWara\FeatureFlags\Commands;

use Illuminate\Console\Command;
use MugiWara\FeatureFlags\Contracts\FeatureManager;
use MugiWara\FeatureFlags\Drivers\DatabaseDriver;

class ListFeaturesCommand extends Command
{
    protected $signature = 'feature:list
                            {--tenant= : Filter flags for a specific tenant (database driver only)}';

    protected $description = 'List all feature flags and their current status';

    public function handle(FeatureManager $manager): int
    {
        $driver = $manager->getDriver();

        if ($driver instanceof DatabaseDriver) {
            return $this->listFromDatabase($driver);
        }

        return $this->listFromConfig($manager);
    }

    private function listFromDatabase(DatabaseDriver $driver): int
    {
        $tenant = $this->option('tenant');

        if ($tenant) {
            $driver->setTenant($tenant);
        }

        $rows = \Illuminate\Support\Facades\DB::table('feature_flags')
            ->when($tenant, fn($q) => $q->where(
                fn($q) => $q->where('tenant_id', $tenant)->orWhereNull('tenant_id')
            ), fn($q) => $q->whereNull('tenant_id'))
            ->orderBy('name')
            ->get();

        if ($rows->isEmpty()) {
            $this->components->info('No feature flags found.');
            return self::SUCCESS;
        }

        $this->table(
            ['Name', 'Status', 'Tenant', 'Rollout %', 'Description'],
            $rows->map(function ($row) {
                $metadata = $row->metadata ? json_decode($row->metadata, true) : [];
                $percentage = $metadata['percentage'] ?? null;

                return [
                    $row->name,
                    $row->enabled
                        ? '<fg=green>enabled</>'
                        : '<fg=red>disabled</>',
                    $row->tenant_id ?? '<fg=gray>global</>',
                    $percentage !== null ? "{$percentage}%" : '<fg=gray>—</>',
                    $row->description ?? '<fg=gray>—</>',
                ];
            })->all()
        );

        return self::SUCCESS;
    }

    private function listFromConfig(FeatureManager $manager): int
    {
        $all = $manager->all();

        if (empty($all)) {
            $this->components->info('No feature flags defined in config.');
            return self::SUCCESS;
        }

        $this->table(
            ['Name', 'Status'],
            array_map(fn($name, $enabled) => [
                $name,
                $enabled
                    ? '<fg=green>enabled</>'
                    : '<fg=red>disabled</>',
            ], array_keys($all), $all)
        );

        $this->newLine();
        $this->components->warn('Using config driver — changes made at runtime are not persisted.');

        return self::SUCCESS;
    }
}
