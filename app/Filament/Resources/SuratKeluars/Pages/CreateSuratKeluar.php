<?php

namespace App\Filament\Resources\SuratKeluars\Pages;

use App\Filament\Resources\SuratKeluars\SuratKeluarResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateSuratKeluar extends CreateRecord
{
    protected static string $resource = SuratKeluarResource::class;

    protected static ?string $title = 'Buat Surat';

    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getCreatedNotificationTitle(): ?string
    {
        return "Surat tersimpan dengan nomor {$this->getRecord()->getAttribute('nomor')}";
    }

    protected function getRedirectUrl(): string
    {
        return SuratKeluarResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
