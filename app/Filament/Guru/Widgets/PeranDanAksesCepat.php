<?php

namespace App\Filament\Guru\Widgets;

use App\Filament\Guru\Pages\OrganisasiSaya;
use App\Filament\Guru\Pages\RaporKelasSaya;
use App\Filament\Resources\Jadwals\JadwalResource;
use App\Filament\Resources\PelanggaranSiswas\PelanggaranSiswaResource;
use App\Filament\Resources\Siswas\SiswaResource;
use App\Filament\Resources\SuratKeluars\SuratKeluarResource;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * Shows additional roles/positions held by a teacher on the Guru dashboard with compact quick access cards.
 */
class PeranDanAksesCepat extends Widget
{
    protected static ?int $sort = -2;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.guru.widgets.peran-dan-akses-cepat';

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && static::daftarItem($user) !== [];
    }

    /**
     * @return list<array{jabatan: string, judul: string, deskripsi: string, icon: string, url: string}>
     */
    public function items(): array
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? static::daftarItem($user) : [];
    }

    /**
     * @return list<array{jabatan: string, judul: string, deskripsi: string, icon: string, url: string}>
     */
    protected static function daftarItem(User $user): array
    {
        $items = [];

        if ($user->hasRole(config('filament-shield.super_admin.name'))) {
            $items[] = [
                'jabatan' => 'Super Admin',
                'judul' => 'Panel Admin',
                'deskripsi' => 'Pengaturan, pengguna & backup',
                'icon' => 'heroicon-o-shield-check',
                'url' => Filament::getPanel('admin')->getUrl(),
            ];
        }

        if ($user->hasRole(RoleSeeder::KEPALA_MADRASAH)) {
            $items[] = [
                'jabatan' => 'Kepala Madrasah',
                'judul' => 'Pantau Madrasah',
                'deskripsi' => 'Data induk, keuangan & ujian',
                'icon' => 'heroicon-o-building-office-2',
                'url' => Filament::getPanel('admin')->getUrl(),
            ];
        }

        if ($user->hasRole(RoleSeeder::WK_KURIKULUM)) {
            $items[] = [
                'jabatan' => 'WK Kurikulum',
                'judul' => 'Kelola Kurikulum',
                'deskripsi' => 'Jadwal, mapel & bank soal',
                'icon' => 'heroicon-o-calendar-days',
                'url' => JadwalResource::getUrl(panel: 'admin'),
            ];
        }

        if ($user->hasRole(RoleSeeder::WK_KESISWAAN)) {
            $items[] = [
                'jabatan' => 'WK Kesiswaan',
                'judul' => 'Kelola Kesiswaan',
                'deskripsi' => 'Kedisiplinan, prestasi & SPMB',
                'icon' => 'heroicon-o-trophy',
                'url' => PelanggaranSiswaResource::getUrl(panel: 'admin'),
            ];
        }

        if ($user->hasRole('operator')) {
            $items[] = [
                'jabatan' => 'Operator',
                'judul' => 'Data Induk',
                'deskripsi' => 'Siswa, GTK, kelas & rapor',
                'icon' => 'heroicon-o-circle-stack',
                'url' => SiswaResource::getUrl(panel: 'admin'),
            ];
        }

        if ($user->hasRole(RoleSeeder::TATA_USAHA)) {
            $items[] = [
                'jabatan' => 'Tata Usaha',
                'judul' => 'Persuratan & Arsip',
                'deskripsi' => 'Surat, kop, mutasi & ijazah',
                'icon' => 'heroicon-o-envelope',
                'url' => SuratKeluarResource::getUrl(panel: 'admin'),
            ];
        }

        if ($user->hasRole('bendahara')) {
            $items[] = [
                'jabatan' => 'Bendahara',
                'judul' => 'Panel Bendahara',
                'deskripsi' => 'Tagihan, kas & penggajian',
                'icon' => 'heroicon-o-banknotes',
                'url' => Filament::getPanel('bendahara')->getUrl(),
            ];
        }

        $gtk = $user->gtk;
        if ($gtk !== null) {
            $kelasPerwalian = $gtk->kelasPerwalian()->orderBy('tingkat')->orderBy('nama')->pluck('nama');
            if ($kelasPerwalian->isNotEmpty()) {
                $items[] = [
                    'jabatan' => 'Wali Kelas',
                    'judul' => $kelasPerwalian->implode(', '),
                    'deskripsi' => 'Rapor & leger kelas',
                    'icon' => 'heroicon-o-document-chart-bar',
                    'url' => RaporKelasSaya::getUrl(),
                ];
            }

            $ekskulBinaan = $gtk->ekskulBinaan()->aktif()->orderBy('nama')->pluck('nama');
            if ($ekskulBinaan->isNotEmpty()) {
                $items[] = [
                    'jabatan' => 'Pembina Ekskul',
                    'judul' => $ekskulBinaan->implode(', '),
                    'deskripsi' => 'Anggota, kegiatan & nilai',
                    'icon' => 'heroicon-o-user-group',
                    'url' => OrganisasiSaya::getUrl(),
                ];
            }
        }

        return $items;
    }
}
