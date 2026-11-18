<?php

namespace App\Filament\Resources\PrestasiSiswas\Pages;

use App\Filament\Resources\PrestasiSiswas\PrestasiSiswaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePrestasiSiswas extends ManageRecords
{
    protected static string $resource = PrestasiSiswaResource::class;

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
