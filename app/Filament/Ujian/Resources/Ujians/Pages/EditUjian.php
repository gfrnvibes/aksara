<?php

namespace App\Filament\Ujian\Resources\Ujians\Pages;

use App\Filament\Ujian\Resources\Ujians\UjianResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUjian extends EditRecord
{
    protected static string $resource = UjianResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
