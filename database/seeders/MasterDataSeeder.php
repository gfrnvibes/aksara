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
                'Al-Qur\'an Hadist' => 'QURDIS',
                'Aqidah Akhlak' => 'AKHLAK',
                'Sejarah Kebudayaan Islam' => 'SKI',
                'Fiqih' => 'FQH',
                'Bahasa Arab' => 'BAHAR',
                'Pendidikan Pancasila dan Kewarganegaraan' => 'PPKN',
                'Bahasa Indonesia' => 'BIND',
                'Matematika' => 'MATEMATIKA',
                'Ilmu Pengetahuan Alam' => 'IPA',
                'Ilmu Pengetahuan Sosial' => 'IPS',
                'Bahasa Inggris' => 'BING',
            ],
            KelompokMapel::B->value => [
                'Seni Budaya' => 'SENI',
                'Pendidikan Jasmani, Olahraga dan Kesehatan' => 'PJOK',
                'Prakarya' => 'PRAKARYA',
            ],
        ];

        foreach ($mataPelajaran as $kelompok => $daftarMapel) {
            foreach (array_keys($daftarMapel) as $index => $nama) {
                $mapel = MataPelajaran::query()->firstOrCreate(
                    ['nama' => $nama],
                    ['kelompok' => $kelompok, 'urutan' => $index + 1, 'kkm' => 75, 'singkatan' => $daftarMapel[$nama]],
                );

                // Fill in subjects seeded before abbreviations existed, keeping any abbreviation typed in since.
                if (blank($mapel->singkatan)) {
                    $mapel->update(['singkatan' => $daftarMapel[$nama]]);
                }
            }
        }

        Ekstrakurikuler::query()->firstOrCreate(['nama' => 'Praja Muda Karana (Pramuka)'], ['is_wajib' => true]);
        Ekstrakurikuler::query()->firstOrCreate(['nama' => 'Usaha Kesehatan Sekolah (UKS)']);
    }
}
