<?php

namespace App\Filament\Resources\Jadwals\Pages;

use App\Filament\Resources\Jadwals\JadwalResource;
use App\Models\Jadwal;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ListJadwals extends ManageRecords
{
    protected static string $resource = JadwalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Buat Jadwal Baru')
                ->successRedirectUrl(fn (Jadwal $record): string => AturJadwal::getUrl(['record' => $record])),
        ];
    }
}
