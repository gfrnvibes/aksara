<?php

namespace App\Services\Harian;

use App\Models\PengaturanPresensi;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class LokasiPresensi
{
    /**
     * Haversine distance in meters between two WGS84 coordinates.
     */
    public function hitungJarakMeter(float $lat1, float $lon1, float $lat2, float $lon2): int
    {
        $earthRadius = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return (int) round($earthRadius * $c);
    }

    public function validasiWaktu(PengaturanPresensi $pengaturan, ?Carbon $waktu = null): void
    {
        if (! $pengaturan->aktif) {
            return;
        }

        $sekarang = ($waktu ?? now('Asia/Jakarta'))->copy()->timezone('Asia/Jakarta')->format('H:i');

        if ($sekarang < $pengaturan->jam_mulai || $sekarang > $pengaturan->jam_selesai) {
            throw ValidationException::withMessages([
                'waktu' => "Presensi hanya dibuka pukul {$pengaturan->jam_mulai}–{$pengaturan->jam_selesai} WIB (saat ini {$sekarang} WIB).",
            ]);
        }
    }

    public function validasiLokasi(PengaturanPresensi $pengaturan, ?float $latitude, ?float $longitude): ?int
    {
        if (! $pengaturan->aktif || $pengaturan->latitude === null || $pengaturan->longitude === null) {
            return null;
        }

        if ($latitude === null || $longitude === null) {
            throw ValidationException::withMessages([
                'lokasi' => 'Koordinat lokasi GPS wajib dikirim untuk presensi.',
            ]);
        }

        $jarak = $this->hitungJarakMeter(
            $latitude,
            $longitude,
            $pengaturan->latitude,
            $pengaturan->longitude,
        );

        if ($jarak > $pengaturan->radius_meter) {
            throw ValidationException::withMessages([
                'lokasi' => "Lokasi Anda berada {$jarak} m dari {$pengaturan->nama_lokasi} (maksimal {$pengaturan->radius_meter} m).",
            ]);
        }

        return $jarak;
    }

    /** @return array<string, mixed> */
    public function ringkasanPengaturan(PengaturanPresensi $pengaturan): array
    {
        return [
            'aktif' => $pengaturan->aktif,
            'nama_lokasi' => $pengaturan->nama_lokasi,
            'latitude' => $pengaturan->latitude,
            'longitude' => $pengaturan->longitude,
            'radius_meter' => $pengaturan->radius_meter,
            'jam_mulai' => $pengaturan->jam_mulai,
            'jam_selesai' => $pengaturan->jam_selesai,
            'wajib_foto' => $pengaturan->wajib_foto,
        ];
    }
}
