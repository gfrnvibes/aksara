<?php

namespace App\Filament\Resources\Ppdbs\Pages;

use App\Filament\Actions\ImportPpdbAction;
use App\Filament\Resources\Ppdbs\PpdbResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPpdbs extends ListRecords
{
    protected static string $resource = PpdbResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportPpdbAction::make(),
            CreateAction::make(),
        ];
    }
}
