<?php

namespace App\Filament\Resources\Siswas\Pages;

use App\Filament\Actions\EksporBukuIndukActions;
use App\Filament\Resources\Siswas\SiswaResource;
use BokshornIt\FilamentActivityTimeline\Actions\ActivityTimelineAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditSiswa extends EditRecord
{
    protected static string $resource = SiswaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EksporBukuIndukActions::cetakSatuSiswa()
                ->button(),
            ActivityTimelineAction::make()
                ->label('Riwayat Perubahan')
                ->withRelations(['ayah', 'ibu', 'wali', 'rapor']),
            DeleteAction::make()
                ->modalDescription('Siswa dipindahkan ke data terhapus dan dapat dipulihkan kembali beserta seluruh nilainya.'),
            RestoreAction::make()
                ->label('Pulihkan'),
            ForceDeleteAction::make()
                ->label('Hapus permanen')
                ->modalDescription('Biodata, data orang tua/wali, dan seluruh nilai rapor siswa ini akan dihapus permanen dan tidak dapat dipulihkan.'),
        ];
    }
}
