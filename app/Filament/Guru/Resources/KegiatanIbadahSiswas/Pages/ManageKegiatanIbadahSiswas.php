<?php

namespace App\Filament\Guru\Resources\KegiatanIbadahSiswas\Pages;

use App\Filament\Guru\Resources\KegiatanIbadahSiswas\KegiatanIbadahSiswaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageKegiatanIbadahSiswas extends ManageRecords
{
    protected static string $resource = KegiatanIbadahSiswaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Catat Ibadah Siswa')
                ->mutateDataUsing(function (array $data): array {
                    $data['pencatat_id'] = auth()->id();

                    return $data;
                }),
        ];
    }
}
