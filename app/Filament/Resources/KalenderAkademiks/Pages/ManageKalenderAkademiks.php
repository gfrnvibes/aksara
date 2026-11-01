<?php

namespace App\Filament\Resources\KalenderAkademiks\Pages;

use App\Filament\Resources\KalenderAkademiks\KalenderAkademikResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageKalenderAkademiks extends ManageRecords
{
    protected static string $resource = KalenderAkademikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
