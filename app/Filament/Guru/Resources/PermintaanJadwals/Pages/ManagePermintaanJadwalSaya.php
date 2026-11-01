<?php

namespace App\Filament\Guru\Resources\PermintaanJadwals\Pages;

use App\Filament\Guru\Resources\PermintaanJadwals\PermintaanJadwalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePermintaanJadwalSaya extends ManageRecords
{
    protected static string $resource = PermintaanJadwalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Permintaan')
                ->mutateDataUsing(fn (array $data): array => [...$data, 'gtk_id' => PermintaanJadwalResource::gtk()?->id]),
        ];
    }
}
