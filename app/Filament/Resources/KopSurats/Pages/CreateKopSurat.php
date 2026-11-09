<?php

namespace App\Filament\Resources\KopSurats\Pages;

use App\Filament\Resources\KopSurats\KopSuratResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateKopSurat extends CreateRecord
{
    use MempratinjauKop;

    protected static string $resource = KopSuratResource::class;

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Simpan Draf');
    }

    protected function getHeaderActions(): array
    {
        return [$this->aksiPratinjauPdf()];
    }
}
