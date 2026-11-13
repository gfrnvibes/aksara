<?php

namespace App\Filament\Ujian\Resources\Ujians\Pages;

use App\Filament\Ujian\Resources\Ujians\UjianResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateUjian extends CreateRecord
{
    protected static string $resource = UjianResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'user_id' => Filament::auth()->id()];
    }
}
