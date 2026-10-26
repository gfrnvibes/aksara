<?php

namespace App\Filament\Actions;

use App\Models\Contracts\TerlindungDariHapus;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;

/**
 * Delete actions for master data that explain why a record still in use was not deleted.
 *
 * The models refuse the delete themselves ({@see TerlindungDariHapus}); these actions only word the outcome.
 */
class HapusMasterDataActions
{
    public static function hapus(): DeleteAction
    {
        return DeleteAction::make()
            ->failureNotification(fn (Notification $notification, TerlindungDariHapus $record): Notification => $notification
                ->title('Data tidak dapat dihapus')
                ->body($record->keteranganPemakaian()));
    }

    public static function hapusMassal(): DeleteBulkAction
    {
        return DeleteBulkAction::make()
            ->missingBulkProcessingFailureNotificationMessage(fn (int $failureCount): string => "{$failureCount} data tidak dihapus karena masih dipakai siswa atau rapor. Nonaktifkan data tersebut jika tidak dipakai lagi.");
    }
}
