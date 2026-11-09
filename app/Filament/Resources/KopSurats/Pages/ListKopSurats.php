<?php

namespace App\Filament\Resources\KopSurats\Pages;

use App\Filament\Resources\KopSurats\KopSuratResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKopSurats extends ListRecords
{
    protected static string $resource = KopSuratResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Buat Template Kop')];
    }
}
