<?php

namespace App\Filament\Ujian\Resources\KegiatanUjians\Pages;

use App\Filament\Ujian\Resources\KegiatanUjians\KegiatanUjianResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditKegiatanUjian extends EditRecord
{
    protected static string $resource = KegiatanUjianResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
