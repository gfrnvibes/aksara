<?php

namespace App\Filament\Resources\SuratKeluars\Pages;

use App\Filament\Resources\SuratKeluars\SuratKeluarResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSuratKeluars extends ListRecords
{
    protected static string $resource = SuratKeluarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Buat Surat'),
        ];
    }
}
