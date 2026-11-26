<?php

namespace App\Filament\Akreditasi\Resources\RapatMadrasahs\Pages;

use App\Filament\Akreditasi\Resources\RapatMadrasahs\RapatMadrasahResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRapatMadrasahs extends ManageRecords
{
    protected static string $resource = RapatMadrasahResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Catat Rapat')
                ->mutateDataUsing(function (array $data): array {
                    $data['pencatat_id'] = auth()->id();

                    return $data;
                }),
        ];
    }
}
