<?php

namespace App\Filament\Akreditasi\Pages;

use App\Jobs\SusunDokumenAkreditasiAi;
use App\Models\AkreditasiBukti;
use App\Models\AkreditasiDokumen;
use App\Models\TahunAjaran;
use App\Services\Akreditasi\PetaBanPdm;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Bus;

class DasborAkreditasi extends Dashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Dasbor Akreditasi';

    protected static ?string $title = 'Pusat Akreditasi BAN-PDM';

    protected string $view = 'filament.akreditasi.dasbor';

    public function tahunAktif(): ?TahunAjaran
    {
        return once(fn (): ?TahunAjaran => TahunAjaran::query()->where('is_aktif', true)->first()
            ?? TahunAjaran::query()->orderByDesc('nama')->first());
    }

    public function bolehKelola(): bool
    {
        return once(fn (): bool => Filament::auth()->user()?->can('Create:AkreditasiDokumen') ?? false);
    }

    /**
     * @return array{dw_selesai: int, dw_total: int, dka_selesai: int, dka_total: int, bukti_total: int, persen_keseluruhan: int}
     */
    public function kesiapan(): array
    {
        return once(function (): array {
            $tahun = $this->tahunAktif();
            if ($tahun === null) {
                return ['dw_selesai' => 0, 'dw_total' => 6, 'dka_selesai' => 0, 'dka_total' => 14, 'bukti_total' => 0, 'persen_keseluruhan' => 0];
            }

            return app(PetaBanPdm::class)->analisisKesiapan($tahun);
        });
    }

    /**
     * @return Collection<string, AkreditasiDokumen>
     */
    public function dokumenMap(): Collection
    {
        return once(function (): Collection {
            $tahun = $this->tahunAktif();
            if ($tahun === null) {
                return new Collection;
            }

            return AkreditasiDokumen::query()
                ->where('tahun_ajaran_id', $tahun->id)
                ->get()
                ->keyBy('kode');
        });
    }

    /**
     * @return array<string, int>
     */
    public function buktiCountMap(): array
    {
        return once(function (): array {
            $tahun = $this->tahunAktif();
            if ($tahun === null) {
                return [];
            }

            return AkreditasiBukti::query()
                ->where('tahun_ajaran_id', $tahun->id)
                ->selectRaw('kode_butir, count(*) as total')
                ->groupBy('kode_butir')
                ->pluck('total', 'kode_butir')
                ->all();
        });
    }

    protected function getHeaderActions(): array
    {
        $tahun = $this->tahunAktif();

        return [
            Action::make('inisialisasi')
                ->label('Siapkan 20 Dokumen')
                ->icon(Heroicon::OutlinedDocumentPlus)
                ->color(Color::Sky)
                ->visible(fn (): bool => $this->bolehKelola() && $tahun !== null)
                ->action(function () use ($tahun): void {
                    if ($tahun === null) {
                        return;
                    }
                    $dibuat = app(PetaBanPdm::class)->inisialisasiDokumen($tahun, is_numeric(Filament::auth()->id()) ? (int) Filament::auth()->id() : null);
                    Notification::make()->success()->title("{$dibuat} dokumen standar akreditasi disiapkan")->send();
                }),

            Action::make('autoTag')
                ->label('Auto-Tag Bukti')
                ->icon(Heroicon::OutlinedTag)
                ->color('warning')
                ->visible(fn (): bool => $this->bolehKelola() && $tahun !== null)
                ->action(function () use ($tahun): void {
                    if ($tahun === null) {
                        return;
                    }
                    $ditambah = app(PetaBanPdm::class)->autoTagBukti($tahun, is_numeric(Filament::auth()->id()) ? (int) Filament::auth()->id() : null);
                    Notification::make()->success()->title("{$ditambah} bukti otomatis ditandai dari data Aksara")->send();
                }),

            Action::make('generateSemuaAi')
                ->label('Generate Draf AI (Semua Kosong)')
                ->icon(Heroicon::Sparkles)
                ->extraAttributes(['class' => 'btn-ai-generate'])
                ->requiresConfirmation()
                ->modalDescription('Proses penyusunan semua dokumen kosong akan dijalankan di antrean background agar halaman tidak macet.')
                ->visible(fn (): bool => $this->bolehKelola() && $tahun !== null)
                ->action(function () use ($tahun): void {
                    if ($tahun === null) {
                        return;
                    }
                    app(PetaBanPdm::class)->inisialisasiDokumen($tahun, is_numeric(Filament::auth()->id()) ? (int) Filament::auth()->id() : null);
                    $jobs = AkreditasiDokumen::query()
                        ->where('tahun_ajaran_id', $tahun->id)
                        ->where('status', 'belum')
                        ->pluck('id')
                        ->map(fn (int $id): SusunDokumenAkreditasiAi => new SusunDokumenAkreditasiAi($id))
                        ->all();

                    if ($jobs === []) {
                        Notification::make()->info()->title('Semua dokumen sudah memiliki draf')->send();

                        return;
                    }

                    Bus::batch($jobs)->name('akreditasi-ai-'.$tahun->id)->dispatch();

                    Notification::make()
                        ->success()
                        ->title(count($jobs).' dokumen dikirim ke antrean AI')
                        ->body('Pastikan queue worker berjalan (php artisan queue:work). Refresh halaman secara berkala.')
                        ->send();
                }),

            Action::make('unduhBundel')
                ->label('Ekspor Bundel Visitasi (.zip)')
                ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
                ->color(Color::Indigo)
                ->visible(fn (): bool => $tahun !== null)
                ->url(fn (): string => $tahun ? route('akreditasi.bundel', $tahun) : '#')
                ->openUrlInNewTab(),
        ];
    }
}
