<?php

namespace App\Filament\Resources\Rapors\Pages;

use App\Filament\Actions\KunciPresensiRapor;
use App\Filament\Resources\Rapors\RaporResource;
use BokshornIt\FilamentActivityTimeline\Actions\ActivityTimelineAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditRapor extends EditRecord
{
    protected static string $resource = RaporResource::class;

    protected function getHeaderActions(): array
    {
        return [
            KunciPresensiRapor::make(true, fn ($record) => $record),
            KunciPresensiRapor::make(false, fn ($record) => $record),
            ActivityTimelineAction::make()
                ->label('Riwayat Perubahan')
                ->withRelations(['nilaiRapor', 'nilaiEkstrakurikuler']),
            DeleteAction::make()
                ->modalDescription('Rapor dipindahkan ke data terhapus dan dapat dipulihkan kembali beserta seluruh nilainya.'),
            RestoreAction::make()
                ->label('Pulihkan'),
            ForceDeleteAction::make()
                ->label('Hapus permanen')
                ->modalDescription('Rapor beserta seluruh nilai mata pelajaran dan ekstrakurikulernya akan dihapus permanen dan tidak dapat dipulihkan.'),
        ];
    }
}
