<?php

namespace App\Filament\Ujian\Resources\Soals\Pages;

use App\Filament\Resources\Kelas\KelasResource;
use App\Filament\Ujian\Resources\Soals\SoalResource;
use App\Models\MataPelajaran;
use App\Services\Gemini\KlienGemini;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The questions of one subject, with a tab per grade.
 */
class ListSoals extends ListRecords
{
    protected static string $resource = SoalResource::class;

    public int $mapel;

    public function mount(int $mapel = 0): void
    {
        abort_unless(MataPelajaran::query()->whereKey($mapel)->exists(), 404);

        $this->mapel = $mapel;

        parent::mount();
    }

    public function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn (Builder $query) => $query->where('mata_pelajaran_id', $this->mapel));
    }

    /**
     * "Semua" plus a tab for each grade the user teaches the subject in, or already has questions for.
     */
    public function getTabs(): array
    {
        $jumlah = SoalResource::getEloquentQuery()
            ->where('mata_pelajaran_id', $this->mapel)
            ->toBase()
            ->selectRaw('tingkat, count(*) as total')
            ->groupBy('tingkat')
            ->pluck('total', 'tingkat');

        $tingkat = collect(SoalResource::pasanganMapelKelas())
            ->filter(fn (array $p): bool => $p[0] === $this->mapel)
            ->pluck(1)
            ->merge($jumlah->keys()->map(fn (mixed $t): int => (int) $t));

        $tabs = ['semua' => Tab::make('Semua')->badge($jumlah->sum())];

        foreach (KelasResource::getTingkatOptions() as $kode => $label) {
            if ($tingkat->contains($kode)) {
                $tabs["kelas-{$kode}"] = Tab::make("Kelas {$label}")
                    ->badge((int) ($jumlah[$kode] ?? 0))
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('tingkat', $kode));
            }
        }

        return $tabs;
    }

    /**
     * The grade of the open tab, or null on "Semua".
     */
    public function tingkatAktif(): ?int
    {
        return str_starts_with((string) $this->activeTab, 'kelas-') ? (int) substr((string) $this->activeTab, 6) : null;
    }

    public function getTitle(): string
    {
        return SoalResource::labelKelompok($this->mapel);
    }

    public function getBreadcrumbs(): array
    {
        return [SoalResource::getUrl() => 'Bank Soal', $this->getTitle()];
    }

    /**
     * Whether the user may add questions here, not just read the subject's shared ones.
     */
    public function bolehMenulis(): bool
    {
        return SoalResource::canCreate() && array_key_exists($this->mapel, SoalResource::pilihanMapel());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('buatDenganAi')
                ->label('Buat dengan AI')
                ->icon(Heroicon::Sparkles)
                ->color('gray')
                ->url(fn (): string => SoalResource::getUrl('ai', array_filter(['mapel' => $this->mapel, 'tingkat' => $this->tingkatAktif()])))
                ->visible(fn (): bool => KlienGemini::aktif() && $this->bolehMenulis()),
            CreateAction::make()
                ->url(fn (): string => SoalResource::getUrl('create', array_filter(['mapel' => $this->mapel, 'tingkat' => $this->tingkatAktif()])))
                ->visible(fn (): bool => $this->bolehMenulis()),
        ];
    }
}
