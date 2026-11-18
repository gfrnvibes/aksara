<?php

namespace App\Filament\Resources\PelanggaranSiswas\Pages;

use App\Filament\Resources\PelanggaranSiswas\PelanggaranSiswaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePelanggaranSiswas extends ManageRecords
{
    protected static string $resource = PelanggaranSiswaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateDataUsing(function (array $data): array {
                    $data['pencatat_id'] = auth()->id();

                    return $data;
                }),
        ];
    }
}
