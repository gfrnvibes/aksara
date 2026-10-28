<?php

namespace App\Filament\Resources\MutasiSiswas\Pages;

use App\Filament\Resources\MutasiSiswas\MutasiSiswaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMutasiSiswas extends ManageRecords
{
    protected static string $resource = MutasiSiswaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Catat Mutasi'),
        ];
    }
}
