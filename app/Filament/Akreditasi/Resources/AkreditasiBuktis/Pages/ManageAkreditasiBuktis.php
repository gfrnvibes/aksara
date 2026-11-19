<?php

namespace App\Filament\Akreditasi\Resources\AkreditasiBuktis\Pages;

use App\Filament\Akreditasi\Resources\AkreditasiBuktis\AkreditasiBuktiResource;
use App\Models\TahunAjaran;
use App\Services\Akreditasi\PetaBanPdm;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageAkreditasiBuktis extends ManageRecords
{
    protected static string $resource = AkreditasiBuktiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('autoTag')
                ->label('Auto-Tag dari Data Aksara')
                ->icon(Heroicon::OutlinedTag)
                ->color('warning')
                ->visible(fn (): bool => Filament::auth()->user()?->can('Create:AkreditasiBukti') ?? false)
                ->action(function (): void {
                    $tahun = TahunAjaran::query()->where('is_aktif', true)->first()
                        ?? TahunAjaran::query()->orderByDesc('nama')->first();

                    if ($tahun === null) {
                        return;
                    }

                    $ditambah = app(PetaBanPdm::class)->autoTagBukti($tahun, is_numeric(Filament::auth()->id()) ? (int) Filament::auth()->id() : null);
                    Notification::make()->success()->title("{$ditambah} bukti otomatis ditandai")->send();
                }),
            CreateAction::make()
                ->mutateDataUsing(fn (array $data): array => [...$data, 'user_id' => Filament::auth()->id()]),
        ];
    }
}
