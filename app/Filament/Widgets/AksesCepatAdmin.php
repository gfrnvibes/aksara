<?php

namespace App\Filament\Widgets;

use App\Filament\Guru\Pages\RekapHarian;
use App\Filament\Pages\BackupData;
use App\Filament\Pages\LegerNilai;
use App\Filament\Resources\BukuPerpustakaans\BukuPerpustakaanResource;
use App\Filament\Resources\Gtks\GtkResource;
use App\Filament\Resources\PelanggaranSiswas\PelanggaranSiswaResource;
use App\Filament\Resources\Ppdbs\PpdbResource;
use App\Filament\Resources\SuratMasuks\SuratMasukResource;
use App\Models\BukuPerpustakaan;
use App\Models\Gtk;
use App\Models\JurnalMengajar;
use App\Models\PelanggaranSiswa;
use App\Models\Ppdb;
use App\Models\PresensiGtk;
use App\Models\PrestasiSiswa;
use App\Models\RiwayatBackup;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\TahunAjaran;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class AksesCepatAdmin extends Widget
{
    protected static ?int $sort = -3;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.akses-cepat-admin';

    /**
     * @return list<array{label: string, url: string, ikon: string, warna: string}>
     */
    public function daftarPanel(): array
    {
        $user = Filament::auth()->user();
        if ($user === null) {
            return [];
        }

        $definisi = [
            'guru' => ['label' => 'Panel Guru', 'ikon' => 'heroicon-o-academic-cap', 'warna' => 'emerald'],
            'ujian' => ['label' => 'Panel Ujian', 'ikon' => 'heroicon-o-clipboard-document-check', 'warna' => 'indigo'],
            'bendahara' => ['label' => 'Panel Bendahara', 'ikon' => 'heroicon-o-banknotes', 'warna' => 'amber'],
            'akreditasi' => ['label' => 'Panel Akreditasi', 'ikon' => 'heroicon-o-shield-check', 'warna' => 'sky'],
        ];

        $hasil = [];
        foreach ($definisi as $id => $info) {
            $panel = Filament::getPanel($id);
            if ($user->canAccessPanel($panel)) {
                $hasil[] = [
                    'label' => $info['label'],
                    'url' => $panel->getUrl(),
                    'ikon' => $info['ikon'],
                    'warna' => $info['warna'],
                ];
            }
        }

        return $hasil;
    }

    /**
     * @return list<array{judul: string, nilai: string, keterangan: string, url: string, ikon: string}>
     */
    public function kartuAksesCepat(): array
    {
        $user = Filament::auth()->user();
        if ($user === null) {
            return [];
        }

        $tahunAktif = TahunAjaran::query()->where('is_aktif', true)->first();
        $kartu = [];

        if ($user->can('viewAny', Gtk::class)) {
            $gtkAktif = Gtk::query()->where('is_aktif', true)->count();
            $tanpaAkun = Gtk::query()->where('is_aktif', true)->whereNull('user_id')->count();
            $kartu[] = [
                'judul' => 'GTK & Pegawai',
                'nilai' => number_format($gtkAktif).' Aktif',
                'keterangan' => $tanpaAkun > 0 ? "{$tanpaAkun} belum punya akun" : 'Semua punya akun login',
                'url' => GtkResource::getUrl(),
                'ikon' => 'heroicon-o-users',
            ];
        }

        if ($user->can('viewAny', Ppdb::class)) {
            $query = Ppdb::query()->when($tahunAktif, fn ($q) => $q->whereBelongsTo($tahunAktif));
            $totalPpdb = (clone $query)->count();
            $menunggu = (clone $query)->where('status', 'menunggu')->count();
            $kartu[] = [
                'judul' => 'SPMB / PPDB',
                'nilai' => number_format($totalPpdb).' Pendaftar',
                'keterangan' => $menunggu > 0 ? "{$menunggu} menunggu verifikasi" : ($tahunAktif ? $tahunAktif->nama : 'Semua pendaftar'),
                'url' => PpdbResource::getUrl(),
                'ikon' => 'heroicon-o-user-plus',
            ];
        }

        if ($user->can('viewAny', SuratMasuk::class)) {
            $masuk = SuratMasuk::query()->count();
            $keluar = SuratKeluar::query()->count();
            $kartu[] = [
                'judul' => 'Persuratan',
                'nilai' => number_format($masuk + $keluar).' Surat',
                'keterangan' => "{$masuk} masuk · {$keluar} keluar",
                'url' => SuratMasukResource::getUrl(),
                'ikon' => 'heroicon-o-envelope',
            ];
        }

        if ($user->can('viewAny', BukuPerpustakaan::class)) {
            $bukuAktif = BukuPerpustakaan::query()->where('is_aktif', true)->count();
            $dibaca = (int) BukuPerpustakaan::query()->where('is_aktif', true)->sum('jumlah_dibaca');
            $kartu[] = [
                'judul' => 'Perpustakaan',
                'nilai' => number_format($bukuAktif).' E-Book',
                'keterangan' => number_format($dibaca).' kali dibaca',
                'url' => BukuPerpustakaanResource::getUrl(),
                'ikon' => 'heroicon-o-book-open',
            ];
        }

        if (RekapHarian::canAccess()) {
            $hadirHariIni = PresensiGtk::query()->whereDate('tanggal', today())->count();
            $jurnalHariIni = JurnalMengajar::query()->whereDate('tanggal', today())->count();
            $kartu[] = [
                'judul' => 'Rekap Harian',
                'nilai' => number_format($hadirHariIni).' Presensi',
                'keterangan' => "{$jurnalHariIni} jurnal mengajar hari ini",
                'url' => RekapHarian::getUrl(),
                'ikon' => 'heroicon-o-calendar-days',
            ];
        }

        if ($user->can('viewAny', PelanggaranSiswa::class)) {
            $pelanggaran = PelanggaranSiswa::query()->when($tahunAktif, fn ($q) => $q->whereBelongsTo($tahunAktif))->count();
            $prestasi = PrestasiSiswa::query()->when($tahunAktif, fn ($q) => $q->whereBelongsTo($tahunAktif))->count();
            $kartu[] = [
                'judul' => 'Pembinaan Siswa',
                'nilai' => number_format($prestasi).' Prestasi',
                'keterangan' => "{$pelanggaran} catatan pelanggaran",
                'url' => PelanggaranSiswaResource::getUrl(),
                'ikon' => 'heroicon-o-trophy',
            ];
        }

        if (LegerNilai::canAccess()) {
            $kartu[] = [
                'judul' => 'Leger Nilai',
                'nilai' => $tahunAktif ? $tahunAktif->nama : 'Leger Kelas',
                'keterangan' => 'Rekap nilai & peringkat kelas',
                'url' => LegerNilai::getUrl(),
                'ikon' => 'heroicon-o-table-cells',
            ];
        }

        if (BackupData::canAccess()) {
            $terakhir = RiwayatBackup::query()->latest()->first();
            $kartu[] = [
                'judul' => 'Backup Data',
                'nilai' => $terakhir ? $terakhir->created_at?->diffForHumans() ?? 'Tersedia' : 'Belum ada',
                'keterangan' => $terakhir ? 'Status: '.strtoupper((string) $terakhir->status) : 'Kelola cadangan sistem',
                'url' => BackupData::getUrl(),
                'ikon' => 'heroicon-o-circle-stack',
            ];
        }

        return $kartu;
    }
}
