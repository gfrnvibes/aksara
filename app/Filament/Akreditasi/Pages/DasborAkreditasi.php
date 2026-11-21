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
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

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
     * @return array{
     *     dw_selesai: int,
     *     dw_final: int,
     *     dw_total: int,
     *     dka_selesai: int,
     *     dka_final: int,
     *     dka_total: int,
     *     bukti_total: int,
     *     butir_terpenuhi: int,
     *     persen_admin_guru: int,
     *     persen_keseluruhan: int
     * }
     */
    public function kesiapan(): array
    {
        return once(function (): array {
            $tahun = $this->tahunAktif();
            if ($tahun === null) {
                return [
                    'dw_selesai' => 0,
                    'dw_final' => 0,
                    'dw_total' => 6,
                    'dka_selesai' => 0,
                    'dka_final' => 0,
                    'dka_total' => 14,
                    'bukti_total' => 0,
                    'butir_terpenuhi' => 0,
                    'persen_admin_guru' => 0,
                    'persen_keseluruhan' => 0,
                ];
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

    public static function kunciBatchAktif(int $tahunId): string
    {
        return "akreditasi-ai:tahun:{$tahunId}:batch";
    }

    public function sembunyikanProgresAi(): void
    {
        $tahun = $this->tahunAktif();
        if ($tahun !== null) {
            Cache::forget(static::kunciBatchAktif($tahun->id));
        }
    }

    public function cekProgresAi(): void
    {
        $this->statusProgresAi();
    }

    /**
     * @return array{
     *     batch_id: string,
     *     total: int,
     *     selesai: int,
     *     gagal: int,
     *     persen: int,
     *     estimasi: string,
     *     antrean_macet: bool
     * }|null
     */
    public function statusProgresAi(): ?array
    {
        $tahun = $this->tahunAktif();
        if ($tahun === null) {
            return null;
        }

        $info = Cache::get(static::kunciBatchAktif($tahun->id));
        if (! is_array($info) || ! is_string($info['batch_id'] ?? null)) {
            return null;
        }

        $batch = Bus::findBatch($info['batch_id']);
        if ($batch === null) {
            Cache::forget(static::kunciBatchAktif($tahun->id));

            return null;
        }

        if ($batch->finished()) {
            $this->selesaikanBatchAi($batch, $tahun->id);

            return null;
        }

        $total = max(1, $batch->totalJobs);
        $selesai = $batch->processedJobs();
        $gagal = $batch->failedJobs;
        $persen = $batch->progress();

        $sekarang = now()->getTimestamp();
        $dibuatPada = $info['dibuat_pada'] ?? $sekarang;
        $umurDetik = $sekarang - (is_int($dibuatPada) ? $dibuatPada : $sekarang);
        $antreanMacet = $batch->pendingJobs === $batch->totalJobs && $umurDetik > 15;

        $estimasi = $antreanMacet
            ? 'Antrean belum bergerak — pastikan worker aktif (php artisan queue:work).'
            : "Menyusun draf AI di latar belakang ({$selesai} dari {$total} dokumen selesai)...";

        return [
            'batch_id' => $batch->id,
            'total' => $total,
            'selesai' => $selesai,
            'gagal' => $gagal,
            'persen' => $persen,
            'estimasi' => $estimasi,
            'antrean_macet' => $antreanMacet,
        ];
    }

    protected function selesaikanBatchAi(Batch $batch, int $tahunId): void
    {
        Cache::forget(static::kunciBatchAktif($tahunId));

        $berhasil = max(0, $batch->totalJobs - $batch->failedJobs);

        if ($batch->failedJobs > 0) {
            Notification::make()
                ->warning()
                ->title('Penyusunan dokumen AI selesai sebagian')
                ->body("{$berhasil} dokumen berhasil disusun, {$batch->failedJobs} gagal.")
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title("Semua {$berhasil} dokumen AI selesai disusun")
            ->send();
    }

    protected function getHeaderActions(): array
    {
        $tahun = $this->tahunAktif();

        return [
            Action::make('generateSemuaAi')
                ->label('Generate Draf AI')
                ->icon(Heroicon::Sparkles)
                ->tooltip('Generate semua dokumen yang belum memiliki draf dengan bantuan AI.')
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

                    $batch = Bus::batch($jobs)->name('akreditasi-ai-'.$tahun->id)->dispatch();

                    Cache::put(static::kunciBatchAktif($tahun->id), [
                        'batch_id' => $batch->id,
                        'dibuat_pada' => now()->timestamp,
                    ], now()->addHours(2));

                    Notification::make()
                        ->success()
                        ->title(count($jobs).' dokumen dikirim ke antrean AI')
                        ->body('Progres penyusunan akan diperbarui otomatis di halaman ini.')
                        ->send();
                }),

            Action::make('inisialisasi')
                ->label('Siapkan 20 Dokumen')
                ->icon(Heroicon::OutlinedDocumentPlus)
                ->tooltip('Siapkan dokumen standar akreditasi yang belum ada di database. Dokumen yang sudah ada tidak akan dihapus.')
                ->color('gray')
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
                ->tooltip('Otomatis menandai bukti yang sudah ada di database dengan butir-butir akreditasi yang sesuai.')
                ->color('gray')
                ->visible(fn (): bool => $this->bolehKelola() && $tahun !== null)
                ->action(function () use ($tahun): void {
                    if ($tahun === null) {
                        return;
                    }
                    $ditambah = app(PetaBanPdm::class)->autoTagBukti($tahun, is_numeric(Filament::auth()->id()) ? (int) Filament::auth()->id() : null);
                    Notification::make()->success()->title("{$ditambah} bukti otomatis ditandai dari data Aksara")->send();
                }),

            Action::make('unduhBundel')
                ->label('Ekspor Bundel Visitasi (.zip)')
                ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
                ->tooltip('Unduh bundel visitasi akreditasi dalam format .zip. Bundel ini berisi dokumen-dokumen yang sudah siap untuk diunggah ke sistem BAN-PDM.')
                ->color('gray')
                ->visible(fn (): bool => $tahun !== null)
                ->url(fn (): string => $tahun ? route('akreditasi.bundel', $tahun) : '#')
                ->openUrlInNewTab(),
        ];
    }
}
