<?php

namespace App\Filament\Resources\GaleriKegiatans\Pages;

use App\Filament\Resources\GaleriKegiatans\GaleriKegiatanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGaleriKegiatans extends ListRecords
{
    protected static string $resource = GaleriKegiatanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Unggah Kegiatan Baru'),
        ];
    }
}
