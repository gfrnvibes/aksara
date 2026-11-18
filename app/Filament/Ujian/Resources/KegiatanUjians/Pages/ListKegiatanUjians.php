<?php

namespace App\Filament\Ujian\Resources\KegiatanUjians\Pages;

use App\Filament\Ujian\Resources\KegiatanUjians\KegiatanUjianResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKegiatanUjians extends ListRecords
{
    protected static string $resource = KegiatanUjianResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
