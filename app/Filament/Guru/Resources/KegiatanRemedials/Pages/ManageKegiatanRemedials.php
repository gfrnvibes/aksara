<?php

namespace App\Filament\Guru\Resources\KegiatanRemedials\Pages;

use App\Filament\Guru\Resources\KegiatanRemedials\KegiatanRemedialResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageKegiatanRemedials extends ManageRecords
{
    protected static string $resource = KegiatanRemedialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Catat Remedial / Pengayaan')
                ->mutateDataUsing(function (array $data): array {
                    $data['pencatat_id'] = auth()->id();

                    return $data;
                }),
        ];
    }
}
