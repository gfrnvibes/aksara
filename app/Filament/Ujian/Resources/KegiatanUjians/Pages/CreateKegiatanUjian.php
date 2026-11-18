<?php

namespace App\Filament\Ujian\Resources\KegiatanUjians\Pages;

use App\Filament\Ujian\Resources\KegiatanUjians\KegiatanUjianResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateKegiatanUjian extends CreateRecord
{
    protected static string $resource = KegiatanUjianResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Filament::auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('denah', ['record' => $this->getRecord()]);
    }
}
