<?php

namespace App\Filament\Resources\SuratMasuks\Pages;

use App\Filament\Resources\SuratMasuks\SuratMasukResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSuratMasuks extends ManageRecords
{
    protected static string $resource = SuratMasukResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Catat Surat Masuk'),
        ];
    }
}
