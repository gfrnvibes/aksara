<?php

namespace App\Filament\Akreditasi\Resources\PelatihanGtks\Pages;

use App\Filament\Akreditasi\Resources\PelatihanGtks\PelatihanGtkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePelatihanGtks extends ManageRecords
{
    protected static string $resource = PelatihanGtkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Catat Pelatihan GTK')
                ->mutateDataUsing(function (array $data): array {
                    $data['pencatat_id'] = auth()->id();

                    return $data;
                }),
        ];
    }
}
