<?php

namespace App\Filament\Resources\GaleriKegiatans\Pages;

use App\Filament\Resources\GaleriKegiatans\GaleriKegiatanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGaleriKegiatan extends EditRecord
{
    protected static string $resource = GaleriKegiatanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
