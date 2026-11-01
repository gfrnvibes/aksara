<?php

namespace App\Filament\Resources\PermintaanJadwals\Pages;

use App\Filament\Resources\PermintaanJadwals\PermintaanJadwalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePermintaanJadwals extends ManageRecords
{
    protected static string $resource = PermintaanJadwalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
