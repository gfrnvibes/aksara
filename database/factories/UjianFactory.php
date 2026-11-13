<?php

namespace Database\Factories;

use App\Enums\Semester;
use App\Models\MataPelajaran;
use App\Models\TahunAjaran;
use App\Models\Ujian;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ujian>
 */
class UjianFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama' => 'Penilaian Akhir Semester',
            'tahun_ajaran_id' => TahunAjaran::factory(),
            'semester' => Semester::Ganjil,
            'mata_pelajaran_id' => MataPelajaran::factory(),
            'tingkat' => 7,
            'durasi_menit' => 90,
            'jumlah_paket' => 1,
        ];
    }
}
