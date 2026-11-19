<?php

namespace App\Filament\Akreditasi\Resources\AkreditasiDokumens\Pages;

use App\Filament\Akreditasi\Resources\AkreditasiDokumens\AkreditasiDokumenResource;
use App\Models\TahunAjaran;
use App\Services\Akreditasi\PetaBanPdm;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListAkreditasiDokumens extends ListRecords
{
    protected static string $resource = AkreditasiDokumenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('inisialisasi')
                ->label('Siapkan 20 Dokumen Standar')
                ->icon(Heroicon::OutlinedDocumentPlus)
                ->visible(fn (): bool => Filament::auth()->user()?->can('Create:AkreditasiDokumen') ?? false)
                ->action(function (): void {
                    $tahun = TahunAjaran::query()->where('is_aktif', true)->first()
                        ?? TahunAjaran::query()->orderByDesc('nama')->first();

                    if ($tahun === null) {
                        Notification::make()->warning()->title('Belum ada Tahun Ajaran')->send();

                        return;
                    }

                    $dibuat = app(PetaBanPdm::class)->inisialisasiDokumen($tahun, is_numeric(Filament::auth()->id()) ? (int) Filament::auth()->id() : null);
                    Notification::make()->success()->title("{$dibuat} dokumen standar akreditasi disiapkan")->send();
                }),
        ];
    }
}
