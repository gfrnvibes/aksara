<?php

namespace App\Filament\Resources\MataPelajarans\Pages;

use App\Filament\Resources\MataPelajarans\MataPelajaranResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMataPelajarans extends ManageRecords
{
    protected static string $resource = MataPelajaranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
