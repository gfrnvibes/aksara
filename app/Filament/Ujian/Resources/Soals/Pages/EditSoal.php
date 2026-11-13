<?php

namespace App\Filament\Ujian\Resources\Soals\Pages;

use App\Filament\Actions\HapusMasterDataActions;
use App\Filament\Ujian\Resources\Soals\SoalResource;
use App\Models\Soal;
use Filament\Resources\Pages\EditRecord;

class EditSoal extends EditRecord
{
    protected static string $resource = SoalResource::class;

    public function getBreadcrumbs(): array
    {
        /** @var Soal $soal */
        $soal = $this->getRecord();

        return [
            SoalResource::getUrl() => 'Bank Soal',
            SoalResource::urlKelompok($soal->mata_pelajaran_id, $soal->tingkat) => SoalResource::labelKelompok($soal->mata_pelajaran_id),
            'Ubah Soal',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [HapusMasterDataActions::hapus()->successRedirectUrl(fn (Soal $record): string => SoalResource::urlKelompok($record->mata_pelajaran_id, $record->tingkat))];
    }

    protected function getRedirectUrl(): string
    {
        /** @var Soal $soal */
        $soal = $this->getRecord();

        return SoalResource::urlKelompok($soal->mata_pelajaran_id, $soal->tingkat);
    }
}
