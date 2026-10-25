<?php

namespace Database\Seeders;

use App\Enums\KelompokMapel;
use App\Models\Ekstrakurikuler;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    /**
     * Seed the reference data used by a Madrasah Tsanawiyah report card.
     */
    public function run(): void
    {
        $tahunMulai = now()->month >= 7 ? now()->year : now()->year - 1;

        foreach (range(2, 0) as $mundur) {
            TahunAjaran::query()->firstOrCreate(
                ['nama' => ($tahunMulai - $mundur).'/'.($tahunMulai - $mundur + 1)],
                ['is_aktif' => $mundur === 0],
            );
        }

        foreach ([7 => 'VII', 8 => 'VIII', 9 => 'IX'] as $tingkat => $romawi) {
            foreach (['A', 'B'] as $rombel) {
                Kelas::query()->firstOrCreate(['nama' => "{$romawi}-{$rombel}"], ['tingkat' => $tingkat]);
            }
        }

        $mataPelajaran = [
            KelompokMapel::A->value => [
                'Al-Qur\'an Hadist',
                'Aqidah Akhlak',
                'Sejarah Kebudayaan Islam',
                'Fiqih',
                'Bahasa Arab',
                'Pendidikan Pancasila dan Kewarganegaraan',
                'Bahasa Indonesia',
                'Matematika',
                'Ilmu Pengetahuan Alam',
                'Ilmu Pengetahuan Sosial',
                'Bahasa Inggris',
            ],
            KelompokMapel::B->value => [
                'Seni Budaya',
                'Pendidikan Jasmani, Olahraga dan Kesehatan',
                'Prakarya',
            ],
        ];

        foreach ($mataPelajaran as $kelompok => $daftarNama) {
            foreach ($daftarNama as $index => $nama) {
                MataPelajaran::query()->firstOrCreate(
                    ['nama' => $nama],
                    ['kelompok' => $kelompok, 'urutan' => $index + 1, 'kkm' => 75],
                );
            }
        }

        Ekstrakurikuler::query()->firstOrCreate(['nama' => 'Praja Muda Karana (Pramuka)'], ['is_wajib' => true]);
        Ekstrakurikuler::query()->firstOrCreate(['nama' => 'Usaha Kesehatan Sekolah (UKS)']);
    }
}
