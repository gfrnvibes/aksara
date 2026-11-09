<?php

namespace App\Filament\Resources\Ppdbs\Pages;

use App\Filament\Resources\Ppdbs\PpdbResource;
use BokshornIt\FilamentActivityTimeline\Actions\ActivityTimelineAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPpdb extends EditRecord
{
    protected static string $resource = PpdbResource::class;

    protected function getHeaderActions(): array
    {
        return [ActivityTimelineAction::make()->label('Riwayat Perubahan'), DeleteAction::make()
            ->modalDescription('Data pendaftaran dan berkasnya dihapus permanen. Siswa yang sudah dibuat dari pendaftaran ini tetap ada di Buku Induk.')];
    }
}
