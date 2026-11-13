<?php

namespace App\Filament\Guru\Widgets;

use App\Filament\Guru\Pages\BukuPiket;
use App\Filament\Guru\Pages\KehadiranGtk;
use App\Filament\Guru\Pages\PresensiKelas;
use App\Models\PresensiGtk;
use App\Models\User;
use App\Services\Harian\AksesHarian;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AksiHarian extends StatsOverviewWidget
{
    protected static ?int $sort = -1;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $user = Filament::auth()->user();
        if (! $user instanceof User || $user->gtk === null) {
            return [];
        }
        $status = PresensiGtk::query()->where('gtk_id', $user->gtk->id)->where('tanggal', AksesHarian::hariIni())->value('status');
        $stats = [Stat::make('Kehadiran saya', PresensiGtk::STATUS[$status] ?? 'Belum tercatat')->description('Buka kehadiran GTK')->icon('heroicon-o-identification')->color($status === 'hadir' ? 'success' : 'warning')->url(KehadiranGtk::getUrl())];
        if ($user->gtk->kelasPerwalian()->exists() || AksesHarian::piket($user, AksesHarian::hariIni())) {
            $stats[] = Stat::make('Presensi kelas', 'Isi kehadiran siswa')->description('Rekap S/I/A mengikuti rapor')->icon('heroicon-o-user-group')->color('info')->url(PresensiKelas::getUrl());
        }
        if (AksesHarian::piket($user, AksesHarian::hariIni())) {
            $stats[] = Stat::make('Piket hari ini', 'Catat kejadian')->description('Kelas kosong, izin, atau terlambat')->icon('heroicon-o-clipboard-document-list')->color('warning')->url(BukuPiket::getUrl());
        }

        return $stats;
    }
}
