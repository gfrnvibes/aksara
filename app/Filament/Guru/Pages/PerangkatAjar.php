<?php

namespace App\Filament\Guru\Pages;

use App\Models\Gtk;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\PembagianMengajar;
use App\Models\PerangkatAjar as PerangkatAjarModel;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\GeminiKurikulumService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use Throwable;
use UnitEnum;

class PerangkatAjar extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static ?string $navigationLabel = 'Perangkat Ajar';

    protected static ?string $title = 'Perangkat Ajar Kurikulum Merdeka';

    protected static ?string $slug = 'perangkat-ajar';

    protected static ?int $navigationSort = 1;

    protected static string|UnitEnum|null $navigationGroup = 'Perangkat Ajar';

    protected string $view = 'filament.guru.perangkat-ajar';

    #[Url]
    public ?int $tahun_ajaran_id = null;

    #[Url]
    public ?int $kelas_id = null;

    #[Url]
    public ?int $mata_pelajaran_id = null;

    #[Url]
    public string $activeTab = 'ame';

    // Identitas & Parameter
    public string $satuan_pendidikan = '';
    public string $kepala_madrasah = '';
    public string $nip_kepala = '';
    public string $guru_pengampu = '';
    public string $nip_guru = '';
    public string $kota_titimangsa = '';
    public string $tanggal_titimangsa = '';
    public string $jenjang = 'MTs';
    public string $kelas_teks = '';
    public string $fase = 'D';
    public string $tahun_pelajaran = '';
    public int $jp_per_minggu = 5;

    // Perangkat Ajar state
    public ?int $perangkatAjarId = null;
    public array $data_ame = [];
    public array $master_tp = [];
    public array $modul_ajars = [];
    public ?string $catatan = null;

    // Modul Ajar Modal
    public bool $modalModulOpen = false;
    public ?string $selectedModulTp = null;
    public ?array $activeModulData = null;

    public bool $isGenerating = false;
    public ?string $generatingTpCode = null;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->gtk !== null;
    }

    protected static function gtk(): ?Gtk
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user->gtk : null;
    }

    public function mount(): void
    {
        $gtk = static::gtk();

        // Cari Tahun Ajaran aktif
        if (! $this->tahun_ajaran_id) {
            $this->tahun_ajaran_id = (int) (TahunAjaran::query()->where('is_aktif', true)->value('id')
                ?? TahunAjaran::query()->orderByDesc('id')->value('id'));
        }

        // Cari default kelas & mapel dari pembagian mengajar guru
        if ($gtk) {
            $penugasanPertama = PembagianMengajar::query()
                ->where('gtk_id', $gtk->id)
                ->when($this->tahun_ajaran_id, fn ($q) => $q->where('tahun_ajaran_id', $this->tahun_ajaran_id))
                ->first();

            if ($penugasanPertama) {
                $this->kelas_id ??= $penugasanPertama->kelas_id;
                $this->mata_pelajaran_id ??= $penugasanPertama->mata_pelajaran_id;
            }
        }

        $this->muatDataPerangkat();
    }

    public function updatedTahunAjaranId(): void
    {
        $this->muatDataPerangkat();
    }

    public function updatedKelasId(): void
    {
        $this->muatDataPerangkat();
    }

    public function updatedMataPelajaranId(): void
    {
        $this->muatDataPerangkat();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    /**
     * Memuat data perangkat ajar dari database untuk kombinasi yang dipilih
     */
    public function muatDataPerangkat(): void
    {
        $gtk = static::gtk();
        $sekolahNama = config('sekolah.nama', 'MTs At-Taufiq Cisurupan');
        $kepalaDefault = config('sekolah.kepala_madrasah', 'Drs. H. Mulyana, M.Pd.');
        $nipKepalaDefault = config('sekolah.nip_kepala_madrasah', '-');
        $kotaDefault = config('sekolah.tempat_tanda_tangan', config('sekolah.kota', 'Cisurupan'));

        $tahun = TahunAjaran::find($this->tahun_ajaran_id);
        $kelas = Kelas::find($this->kelas_id);
        $mapel = MataPelajaran::find($this->mata_pelajaran_id);

        $tingkat = $kelas?->tingkat ?? 7;
        $faseDefault = match (true) {
            $tingkat <= 2 => 'A',
            $tingkat <= 4 => 'B',
            $tingkat <= 6 => 'C',
            $tingkat <= 9 => 'D',
            $tingkat === 10 => 'E',
            default => 'F',
        };

        if ($this->tahun_ajaran_id && $this->kelas_id && $this->mata_pelajaran_id) {
            $perangkat = PerangkatAjarModel::query()
                ->where('tahun_ajaran_id', $this->tahun_ajaran_id)
                ->where('kelas_id', $this->kelas_id)
                ->where('mata_pelajaran_id', $this->mata_pelajaran_id)
                ->first();

            if ($perangkat) {
                $this->perangkatAjarId = $perangkat->id;
                $this->satuan_pendidikan = $perangkat->satuan_pendidikan ?: $sekolahNama;
                $this->kepala_madrasah = $perangkat->kepala_madrasah ?: $kepalaDefault;
                $this->nip_kepala = $perangkat->nip_kepala ?: $nipKepalaDefault;
                $this->guru_pengampu = $perangkat->guru_pengampu ?: ($gtk?->nama ?? 'Guru Pengampu');
                $this->nip_guru = $perangkat->nip_guru ?: ($gtk?->nip ?? '-');
                $this->kota_titimangsa = $perangkat->kota_titimangsa ?: $kotaDefault;
                $this->tanggal_titimangsa = $perangkat->tanggal_titimangsa ? $perangkat->tanggal_titimangsa->format('Y-m-d') : now()->format('Y-m-d');
                $this->jenjang = $perangkat->jenjang ?: 'MTs';
                $this->kelas_teks = $perangkat->kelas ?: ($kelas?->nama ?? 'VII');
                $this->fase = $perangkat->fase ?: $faseDefault;
                $this->tahun_pelajaran = $perangkat->tahun_pelajaran ?: ($tahun?->nama ?? '2024/2025');
                $this->jp_per_minggu = $perangkat->jp_per_minggu ?: 5;
                $this->data_ame = $perangkat->data_ame ?? [];
                $this->master_tp = $perangkat->master_tp ?? [];
                $this->modul_ajars = $perangkat->modul_ajars ?? [];
                $this->catatan = $perangkat->catatan;

                return;
            }
        }

        // Default identitas jika belum tersimpan
        $this->perangkatAjarId = null;
        $this->satuan_pendidikan = $sekolahNama;
        $this->kepala_madrasah = $kepalaDefault;
        $this->nip_kepala = $nipKepalaDefault;
        $this->guru_pengampu = $gtk?->nama ?? 'Guru Pengampu';
        $this->nip_guru = $gtk?->nip ?? '-';
        $this->kota_titimangsa = $kotaDefault;
        $this->tanggal_titimangsa = now()->format('Y-m-d');
        $this->jenjang = 'MTs';
        $this->kelas_teks = $kelas?->nama ?? 'VII';
        $this->fase = $faseDefault;
        $this->tahun_pelajaran = $tahun?->nama ?? '2024/2025';
        $this->jp_per_minggu = 5;
        $this->data_ame = [];
        $this->master_tp = [];
        $this->modul_ajars = [];
        $this->catatan = null;
    }

    /**
     * Simpan Perubahan Identitas
     */
    public function simpanIdentitas(): void
    {
        if (! $this->tahun_ajaran_id || ! $this->kelas_id || ! $this->mata_pelajaran_id) {
            Notification::make()->title('Pilih Tahun Ajaran, Kelas, dan Mata Pelajaran terlebih dahulu')->warning()->send();

            return;
        }

        $gtk = static::gtk();

        $perangkat = PerangkatAjarModel::query()->updateOrCreate(
            [
                'tahun_ajaran_id' => $this->tahun_ajaran_id,
                'kelas_id' => $this->kelas_id,
                'mata_pelajaran_id' => $this->mata_pelajaran_id,
            ],
            [
                'gtk_id' => $gtk?->id,
                'satuan_pendidikan' => $this->satuan_pendidikan,
                'kepala_madrasah' => $this->kepala_madrasah,
                'nip_kepala' => $this->nip_kepala,
                'guru_pengampu' => $this->guru_pengampu,
                'nip_guru' => $this->nip_guru,
                'kota_titimangsa' => $this->kota_titimangsa,
                'tanggal_titimangsa' => $this->tanggal_titimangsa ?: now()->toDateString(),
                'jenjang' => $this->jenjang,
                'kelas' => $this->kelas_teks,
                'fase' => $this->fase,
                'tahun_pelajaran' => $this->tahun_pelajaran,
                'jp_per_minggu' => $this->jp_per_minggu,
                'catatan' => $this->catatan,
            ]
        );

        $this->perangkatAjarId = $perangkat->id;

        Notification::make()->title('Identitas & Parameter berhasil disimpan')->success()->send();
    }

    /**
     * Tombol Utama [⚡ Generate & Sinkronkan AME, ATP, PROTA, PROSEM]
     */
    public function generateDanSinkronkan(): void
    {
        if (! $this->tahun_ajaran_id || ! $this->kelas_id || ! $this->mata_pelajaran_id) {
            Notification::make()->title('Pilih Tahun Ajaran, Kelas, dan Mata Pelajaran')->warning()->send();

            return;
        }

        $this->isGenerating = true;

        try {
            $mapel = MataPelajaran::find($this->mata_pelajaran_id);
            $service = app(GeminiKurikulumService::class);

            $params = [
                'satuan_pendidikan' => $this->satuan_pendidikan,
                'kepala_madrasah' => $this->kepala_madrasah,
                'nip_kepala' => $this->nip_kepala,
                'guru_pengampu' => $this->guru_pengampu,
                'nip_guru' => $this->nip_guru,
                'kota_titimangsa' => $this->kota_titimangsa,
                'tanggal_titimangsa' => $this->tanggal_titimangsa,
                'mata_pelajaran' => $mapel?->nama ?? 'Matematika',
                'kelas' => $this->kelas_teks,
                'fase' => $this->fase,
                'tahun_pelajaran' => $this->tahun_pelajaran,
                'jp_per_minggu' => $this->jp_per_minggu,
            ];

            $hasil = $service->generateTahunan($params);

            $this->data_ame = $hasil['analisis_minggu_efektif'] ?? [];
            $this->master_tp = $hasil['master_tp'] ?? [];

            // Simpan ke database
            $gtk = static::gtk();
            $perangkat = PerangkatAjarModel::query()->updateOrCreate(
                [
                    'tahun_ajaran_id' => $this->tahun_ajaran_id,
                    'kelas_id' => $this->kelas_id,
                    'mata_pelajaran_id' => $this->mata_pelajaran_id,
                ],
                [
                    'gtk_id' => $gtk?->id,
                    'satuan_pendidikan' => $this->satuan_pendidikan,
                    'kepala_madrasah' => $this->kepala_madrasah,
                    'nip_kepala' => $this->nip_kepala,
                    'guru_pengampu' => $this->guru_pengampu,
                    'nip_guru' => $this->nip_guru,
                    'kota_titimangsa' => $this->kota_titimangsa,
                    'tanggal_titimangsa' => $this->tanggal_titimangsa ?: now()->toDateString(),
                    'jenjang' => $this->jenjang,
                    'kelas' => $this->kelas_teks,
                    'fase' => $this->fase,
                    'tahun_pelajaran' => $this->tahun_pelajaran,
                    'jp_per_minggu' => $this->jp_per_minggu,
                    'data_ame' => $this->data_ame,
                    'master_tp' => $this->master_tp,
                ]
            );

            $this->perangkatAjarId = $perangkat->id;

            Notification::make()
                ->title('Berhasil Menghasilkan dan Menyinkronkan Perangkat Ajar!')
                ->body('AME, ATP, PROTA, dan PROSEM telah sinkron secara matematis.')
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Gagal menghasilkan perangkat ajar')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->isGenerating = false;
        }
    }

    /**
     * Tombol [✨ Generate Modul Ajar] per baris/kartu TP pada Tab 5
     */
    public function generateModulAjarItem(string $kodeTp): void
    {
        $tpTerpilih = collect($this->master_tp)->firstWhere('kode_tp', $kodeTp);
        if (! $tpTerpilih) {
            Notification::make()->title('Data TP tidak ditemukan')->danger()->send();

            return;
        }

        $this->generatingTpCode = $kodeTp;

        try {
            $mapel = MataPelajaran::find($this->mata_pelajaran_id);
            $service = app(GeminiKurikulumService::class);

            $identitas = [
                'satuan_pendidikan' => $this->satuan_pendidikan,
                'mata_pelajaran' => $mapel?->nama ?? 'Matematika',
                'kelas' => $this->kelas_teks,
                'fase' => $this->fase,
                'tahun_pelajaran' => $this->tahun_pelajaran,
                'guru_pengampu' => $this->guru_pengampu,
                'kepala_madrasah' => $this->kepala_madrasah,
            ];

            $modul = $service->generateModulAjar($identitas, $tpTerpilih, $this->jp_per_minggu);

            $this->modul_ajars[$kodeTp] = $modul;

            // Simpan ke database
            if ($this->perangkatAjarId) {
                PerangkatAjarModel::where('id', $this->perangkatAjarId)->update([
                    'modul_ajars' => $this->modul_ajars,
                ]);
            }

            Notification::make()
                ->title("Modul Ajar TP {$kodeTp} Berhasil Dibuat!")
                ->body('Komponen lengkap 18 bagian, rubrik asesmen, dan LKPD siap dicetak.')
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title("Gagal membuat modul ajar TP {$kodeTp}")
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->generatingTpCode = null;
        }
    }

    /**
     * Membuka Modal Lihat Modul Ajar
     */
    public function bukaModulAjar(string $kodeTp): void
    {
        $this->selectedModulTp = $kodeTp;
        $this->activeModulData = $this->modul_ajars[$kodeTp] ?? null;
        $this->modalModulOpen = true;
    }

    public function tutupModulAjar(): void
    {
        $this->modalModulOpen = false;
        $this->selectedModulTp = null;
        $this->activeModulData = null;
    }

    /**
     * Hitung total JP AME
     */
    public function getTotalJpAmeSem1Property(): int
    {
        return (int) ($this->data_ame['ringkasan']['total_jp_sem_1'] ?? 0);
    }

    public function getTotalJpAmeSem2Property(): int
    {
        return (int) ($this->data_ame['ringkasan']['total_jp_sem_2'] ?? 0);
    }

    public function getTotalJpAmeProperty(): int
    {
        return (int) ($this->data_ame['ringkasan']['total_jp_setahun'] ?? ($this->totalJpAmeSem1 + $this->totalJpAmeSem2));
    }

    /**
     * Hitung total JP TP
     */
    public function getTotalJpTpSem1Property(): int
    {
        return (int) collect($this->master_tp)
            ->filter(fn ($t) => (int) ($t['semester'] ?? 1) === 1)
            ->sum(fn ($t) => (int) ($t['alokasi_jp'] ?? 0));
    }

    public function getTotalJpTpSem2Property(): int
    {
        return (int) collect($this->master_tp)
            ->filter(fn ($t) => (int) ($t['semester'] ?? 1) === 2)
            ->sum(fn ($t) => (int) ($t['alokasi_jp'] ?? 0));
    }

    public function getTotalJpTpProperty(): int
    {
        return (int) collect($this->master_tp)->sum(fn ($t) => (int) ($t['alokasi_jp'] ?? 0));
    }

    /**
     * Hitung total JP PROSEM
     */
    public function getTotalJpProsemSem1Property(): int
    {
        $sum = 0;
        foreach ($this->master_tp as $tp) {
            if ((int) ($tp['semester'] ?? 1) !== 1) {
                continue;
            }
            foreach ($tp['jadwal_prosem'] ?? [] as $bulan => $weeks) {
                if (is_array($weeks)) {
                    $sum += array_sum($weeks);
                }
            }
        }

        return $sum;
    }

    public function getTotalJpProsemSem2Property(): int
    {
        $sum = 0;
        foreach ($this->master_tp as $tp) {
            if ((int) ($tp['semester'] ?? 1) !== 2) {
                continue;
            }
            foreach ($tp['jadwal_prosem'] ?? [] as $bulan => $weeks) {
                if (is_array($weeks)) {
                    $sum += array_sum($weeks);
                }
            }
        }

        return $sum;
    }

    public function getTotalJpProsemProperty(): int
    {
        return $this->totalJpProsemSem1 + $this->totalJpProsemSem2;
    }

    /**
     * Status sinkronisasi real-time
     */
    public function getIsSinkronProperty(): bool
    {
        if (empty($this->master_tp) || empty($this->data_ame)) {
            return false;
        }

        return ($this->totalJpAmeSem1 === $this->totalJpTpSem1)
            && ($this->totalJpAmeSem2 === $this->totalJpTpSem2)
            && ($this->totalJpTpSem1 === $this->totalJpProsemSem1)
            && ($this->totalJpTpSem2 === $this->totalJpProsemSem2)
            && ($this->totalJpAme > 0);
    }

    /**
     * Opsi Pilihan Tahun Ajaran
     */
    public function getOpsiTahunAjaranProperty(): array
    {
        return TahunAjaran::query()->orderByDesc('nama')->pluck('nama', 'id')->all();
    }

    /**
     * Opsi Pilihan Kelas untuk guru saat ini
     */
    public function getOpsiKelasProperty(): array
    {
        $gtk = static::gtk();
        if (! $gtk) {
            return [];
        }

        return PembagianMengajar::query()
            ->where('gtk_id', $gtk->id)
            ->when($this->tahun_ajaran_id, fn ($q) => $q->where('tahun_ajaran_id', $this->tahun_ajaran_id))
            ->join('kelas', 'kelas.id', '=', 'pembagian_mengajars.kelas_id')
            ->orderBy('kelas.tingkat')
            ->orderBy('kelas.nama')
            ->pluck('kelas.nama', 'kelas.id')
            ->all();
    }

    /**
     * Opsi Pilihan Mapel untuk guru saat ini di kelas terpilih
     */
    public function getOpsiMataPelajaranProperty(): array
    {
        $gtk = static::gtk();
        if (! $gtk) {
            return [];
        }

        return PembagianMengajar::query()
            ->where('gtk_id', $gtk->id)
            ->when($this->tahun_ajaran_id, fn ($q) => $q->where('tahun_ajaran_id', $this->tahun_ajaran_id))
            ->when($this->kelas_id, fn ($q) => $q->where('kelas_id', $this->kelas_id))
            ->join('mata_pelajarans', 'mata_pelajarans.id', '=', 'pembagian_mengajars.mata_pelajaran_id')
            ->orderBy('mata_pelajarans.nama')
            ->pluck('mata_pelajarans.nama', 'mata_pelajarans.id')
            ->all();
    }

    /**
     * Tombol-tombol di header halaman
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('cetakPerangkatIni')
                ->label('🖨️ Cetak Perangkat Ini')
                ->color('gray')
                ->visible(fn (): bool => $this->perangkatAjarId !== null)
                ->url(fn (): string => route('perangkat-ajar.cetak-satuan', [
                    'perangkatAjar' => $this->perangkatAjarId ?: 0,
                    'tab' => $this->activeTab,
                    'kodeTp' => $this->selectedModulTp ?? 'all',
                ]))
                ->openUrlInNewTab(),

            Action::make('cetakBundel')
                ->label('📚 Cetak Paket Lengkap (Bundel)')
                ->color('emerald')
                ->visible(fn (): bool => $this->perangkatAjarId !== null)
                ->url(fn (): string => route('perangkat-ajar.cetak-bundel', [
                    'perangkatAjar' => $this->perangkatAjarId ?: 0,
                ]))
                ->openUrlInNewTab(),
        ];
    }
}
