<?php

namespace App\Filament\Resources\Gtks\Pages;

use App\Filament\Actions\ImportBukuIndukActions;
use App\Filament\Resources\Gtks\GtkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageGtks extends ManageRecords
{
    protected static string $resource = GtkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportBukuIndukActions::gtk(),
            CreateAction::make()
                ->modalWidth('3xl'),
        ];
    }
}
