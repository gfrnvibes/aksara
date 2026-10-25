<?php

namespace App\Filament\Resources\Rapors\Pages;

use App\Filament\Actions\EksporBukuIndukActions;
use App\Filament\Actions\ImportBukuIndukActions;
use App\Filament\Resources\Rapors\RaporResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRapors extends ListRecords
{
    protected static string $resource = RaporResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportBukuIndukActions::nilai(),
            EksporBukuIndukActions::exportRapor(),
            CreateAction::make(),
        ];
    }
}
