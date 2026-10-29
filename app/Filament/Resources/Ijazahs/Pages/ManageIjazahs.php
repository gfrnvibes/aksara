<?php

namespace App\Filament\Resources\Ijazahs\Pages;

use App\Filament\Resources\Ijazahs\IjazahResource;
use App\Models\Ijazah;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageIjazahs extends ManageRecords
{
    protected static string $resource = IjazahResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('unggahMassal')
                ->label('Unggah Massal')
                ->icon(Heroicon::OutlinedCloudArrowUp)
                ->color('gray')
                ->url(IjazahResource::getUrl('unggah'))
                ->visible(fn (): bool => IjazahResource::canCreate()),
            CreateAction::make(),
        ];
    }

    public function getSubheading(): ?string
    {
        $jumlah = Ijazah::query()->count();

        return $jumlah === 0 ? null : "{$jumlah} ijazah dari ".Ijazah::query()->distinct()->count('tahun_lulus').' angkatan.';
    }
}
