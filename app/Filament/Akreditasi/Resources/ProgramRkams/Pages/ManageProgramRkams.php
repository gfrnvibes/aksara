<?php

namespace App\Filament\Akreditasi\Resources\ProgramRkams\Pages;

use App\Filament\Akreditasi\Resources\ProgramRkams\ProgramRkamResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProgramRkams extends ManageRecords
{
    protected static string $resource = ProgramRkamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Program RKAM')
                ->mutateDataUsing(function (array $data): array {
                    $data['pencatat_id'] = auth()->id();

                    return $data;
                }),
        ];
    }
}
