<?php

namespace App\Filament\Resources\SuratKeluars\Pages;

use App\Filament\Resources\SuratKeluars\SuratKeluarResource;
use App\Filament\Resources\SuratKeluars\Tables\SuratKeluarsTable;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditSuratKeluar extends EditRecord
{
    protected static string $resource = SuratKeluarResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [
            SuratKeluarsTable::aksiCetak()->button()->color('gray'),
            SuratKeluarsTable::aksiDuplikat(),
            DeleteAction::make()
                ->modalDescription('Nomor surat yang dihapus tidak dipakai ulang.'),
        ];
    }
}
