<?php

namespace App\Services\Harian;

use App\Enums\Hari;
use App\Enums\JenisAgenda;
use App\Models\KalenderAkademik;
use App\Models\Kelas;
use App\Models\PenugasanPiket;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AksesHarian
{
    public static function hariIni(): string
    {
        return now('Asia/Jakarta')->toDateString();
    }

    public static function pengelola(User $user): bool
    {
        return $user->hasRole(config('filament-shield.super_admin.name')) || $user->can('View:PenugasanPiket');
    }

    public static function piket(User $user, string $tanggal): bool
    {
        return static::pengelola($user) || PenugasanPiket::query()->where('tanggal', $tanggal)
            ->whereHas('gtk', fn ($q) => $q->where('user_id', $user->id)->where('is_aktif', true))->exists();
    }

    public static function kelas(User $user, Kelas $kelas, string $tanggal): bool
    {
        return static::piket($user, $tanggal) || ($user->gtk?->is_aktif && $kelas->wali_kelas_id === $user->gtk->id);
    }

    public static function tanggal(string $tanggal, bool $kbm = false): Carbon
    {
        Validator::make(['tanggal' => $tanggal], ['tanggal' => ['required', 'date_format:Y-m-d']])->validate();
        $date = Carbon::parse($tanggal, 'Asia/Jakarta');
        if ($date->toDateString() > static::hariIni()) {
            throw ValidationException::withMessages(['tanggal' => 'Kehadiran tidak boleh dicatat untuk tanggal mendatang.']);
        }
        if ($kbm && (Hari::dariTanggal($date) === null || static::libur($tanggal))) {
            throw ValidationException::withMessages(['tanggal' => 'Tanggal ini hari libur. Presensi belajar tidak dicatat.']);
        }

        return $date;
    }

    public static function tahun(string $tanggal): TahunAjaran
    {
        $date = Carbon::parse($tanggal, 'Asia/Jakarta');
        $year = $date->month >= 7 ? $date->year : $date->year - 1;

        return TahunAjaran::query()->where('nama', $year.'/'.($year + 1))->first()
            ?? throw ValidationException::withMessages(['tanggal' => 'Tahun ajaran untuk tanggal ini belum tersedia.']);
    }

    public static function libur(string $tanggal): bool
    {
        return KalenderAkademik::query()->where('jenis', JenisAgenda::Libur)
            ->where('tahun_ajaran_id', static::tahun($tanggal)->id)
            ->whereDate('tanggal_mulai', '<=', $tanggal)->whereDate('tanggal_selesai', '>=', $tanggal)->exists();
    }
}
