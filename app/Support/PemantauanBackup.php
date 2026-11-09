<?php

namespace App\Support;

use App\Models\RiwayatBackup;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackup;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;

class PemantauanBackup extends FilamentSpatieLaravelBackup
{
    /** @return list<string> */
    public static function getDisks(): array
    {
        // Keep destinations with recorded attempts visible, even when the
        // server's current destination configuration has changed.
        $disks = parent::getDisks();
        foreach (RiwayatBackup::query()->select('disk')->distinct()->get() as $backup) {
            if (! in_array($backup->disk, $disks, true)) {
                $disks[] = $backup->disk;
            }
        }

        return $disks;
    }

    /** @return list<array{name: string, disks: list<string>, health_checks: mixed}> */
    protected static function getMonitorConfigurations(): array
    {
        $name = config()->string('backup.backup.name');
        $monitor = collect(parent::getMonitorConfigurations())->firstWhere('name', $name);

        return [[
            'name' => $name,
            'disks' => static::getDisks(),
            'health_checks' => $monitor['health_checks'] ?? [
                MaximumAgeInDays::class => 1,
                MaximumStorageInMegabytes::class => 5000,
            ],
        ]];
    }
}
