<?php

namespace App\Http\Controllers;

use App\Filament\Pages\BackupData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackup;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UnduhBackupController extends Controller
{
    /**
     * Stream a backup archive from its disk. The link is signed and short-lived; the permission is re-checked here.
     */
    public function __invoke(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can(BackupData::IZIN), 403);

        $disk = (string) $request->query('disk');
        $path = (string) $request->query('path');
        $folderBackup = config()->string('backup.backup.name').'/';

        abort_unless(
            in_array($disk, FilamentSpatieLaravelBackup::getDisks(), true)
                && Str::startsWith($path, $folderBackup)
                && Str::endsWith($path, '.zip')
                && ! Str::contains($path, '..'),
            404,
        );

        $filesystem = Storage::disk($disk);

        abort_unless($filesystem->exists($path), 404);

        activity()->event('backup_diunduh')->withProperties(['disk' => $disk, 'path' => $path])->log('File backup diunduh');

        return $filesystem->download($path, basename($path), ['Content-Type' => 'application/zip']);
    }
}
