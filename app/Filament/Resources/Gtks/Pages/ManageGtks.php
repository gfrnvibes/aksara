<?php

namespace App\Filament\Resources\Gtks\Pages;

use App\Filament\Actions\ImportBukuIndukActions;
use App\Filament\Resources\Gtks\GtkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ManageGtks extends ListRecords
{
    protected static string $resource = GtkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportBukuIndukActions::gtk(),
            CreateAction::make(),
        ];
    }
}
