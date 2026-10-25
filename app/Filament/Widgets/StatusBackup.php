<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\BackupData;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackup;

/**
 * Health of each backup destination (age of the newest backup, storage used, reachability).
 *
 * Shown only on the backup page, not on the dashboard.
 */
class StatusBackup extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Status Monitoring Backup';

    public static function canView(): bool
    {
        return BackupData::canAccess();
    }

    protected function getStats(): array
    {
        return collect(FilamentSpatieLaravelBackup::getBackupDestinationStatusData(BackupData::CACHE_DETIK))
            ->map(fn (array $status): Stat => Stat::make(
                BackupData::labelDisk($status['disk']),
                match (true) {
                    ! $status['reachable'] => 'Tidak terjangkau',
                    $status['healthy'] => 'Sehat',
                    default => 'Bermasalah',
                },
            )
                ->description(
                    $status['reachable']
                        ? "{$status['amount']} backup · terbaru {$status['newest']} · {$status['usedStorage']}"
                        : 'Periksa kredensial atau koneksi ke lokasi penyimpanan.',
                )
                ->icon($status['reachable'] && $status['healthy'] ? 'heroicon-o-shield-check' : 'heroicon-o-exclamation-triangle')
                ->color($status['reachable'] && $status['healthy'] ? 'success' : 'danger'))
            ->all();
    }
}
