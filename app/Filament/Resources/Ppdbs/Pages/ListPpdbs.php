<?php

namespace App\Filament\Resources\Ppdbs\Pages;

use App\Enums\StatusPpdb;
use App\Filament\Actions\ImportPpdbAction;
use App\Filament\Resources\Ppdbs\PpdbResource;
use App\Filament\Resources\TahunAjarans\TahunAjaranResource;
use App\Models\Ppdb;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListPpdbs extends ListRecords
{
    protected static string $resource = PpdbResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('periode')
                ->label('Periode Pendaftaran')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('gray')
                ->url(TahunAjaranResource::getUrl())
                ->tooltip('Tanggal buka dan tutup formulir PPDB diatur per tahun ajaran.')
                ->visible(fn (): bool => TahunAjaranResource::canViewAny()),
            ImportPpdbAction::make(),
            CreateAction::make()
                ->modalWidth(Width::FourExtraLarge),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $jumlah = Ppdb::query()
            ->when($this->tableFilters['tahun_ajaran_id']['value'] ?? null, fn (Builder $query, int|string $tahunAjaranId) => $query->where('tahun_ajaran_id', $tahunAjaranId))
            ->toBase()
            ->selectRaw('status, count(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        $tabs = ['semua' => Tab::make('Semua')->badge($jumlah->sum() ?: null)];

        foreach (StatusPpdb::cases() as $status) {
            $tabs[$status->value] = Tab::make($status->getLabel())
                ->badge($jumlah->get($status->value) ?: null)
                ->badgeColor($status->getColor())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status));
        }

        return $tabs;
    }
}
