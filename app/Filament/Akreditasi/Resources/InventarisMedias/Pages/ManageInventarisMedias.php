<?php

namespace App\Filament\Akreditasi\Resources\InventarisMedias\Pages;

use App\Filament\Akreditasi\Resources\InventarisMedias\InventarisMediaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageInventarisMedias extends ManageRecords
{
    protected static string $resource = InventarisMediaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Media / Alat')
                ->mutateDataUsing(function (array $data): array {
                    $data['pencatat_id'] = auth()->id();

                    return $data;
                }),
        ];
    }
}
