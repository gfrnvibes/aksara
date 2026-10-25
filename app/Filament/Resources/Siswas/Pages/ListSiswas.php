<?php

namespace App\Filament\Resources\Siswas\Pages;

use App\Filament\Actions\EksporBukuIndukActions;
use App\Filament\Actions\ImportBukuIndukActions;
use App\Filament\Resources\Siswas\SiswaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSiswas extends ListRecords
{
    protected static string $resource = SiswaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportBukuIndukActions::group(),
            EksporBukuIndukActions::exportSiswa(),
            EksporBukuIndukActions::cetakSiswa(),
            CreateAction::make(),
        ];
    }
}
