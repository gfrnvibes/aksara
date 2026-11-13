<?php

namespace App\Filament\Ujian\Resources\Ujians\Pages;

use App\Filament\Ujian\Resources\Ujians\UjianResource;
use App\Models\Ujian;
use App\Services\Cbt\PenilaianUjian;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUjian extends EditRecord
{
    protected static string $resource = UjianResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /**
     * Questions or weights may have changed; scores already entered follow them.
     */
    protected function afterSave(): void
    {
        if ($this->getRecord() instanceof Ujian) {
            PenilaianUjian::nilaiUlang($this->getRecord());
        }
    }
}
