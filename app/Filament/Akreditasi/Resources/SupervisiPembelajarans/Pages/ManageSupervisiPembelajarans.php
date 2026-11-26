<?php

namespace App\Filament\Akreditasi\Resources\SupervisiPembelajarans\Pages;

use App\Filament\Akreditasi\Resources\SupervisiPembelajarans\SupervisiPembelajaranResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSupervisiPembelajarans extends ManageRecords
{
    protected static string $resource = SupervisiPembelajaranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Catat Supervisi')
                ->mutateDataUsing(function (array $data): array {
                    $data['pencatat_id'] = auth()->id();

                    return $data;
                }),
        ];
    }
}
