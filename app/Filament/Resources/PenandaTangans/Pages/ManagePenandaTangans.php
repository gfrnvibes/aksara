<?php

namespace App\Filament\Resources\PenandaTangans\Pages;

use App\Filament\Resources\PenandaTangans\PenandaTanganResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePenandaTangans extends ManageRecords
{
    protected static string $resource = PenandaTanganResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
