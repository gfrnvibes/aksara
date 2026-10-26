<?php

namespace Database\Factories;

use App\Models\Kelas;
use App\Models\RiwayatKelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RiwayatKelas>
 */
class RiwayatKelasFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'siswa_id' => Siswa::factory(),
            'tahun_ajaran_id' => TahunAjaran::factory(),
            'kelas_id' => Kelas::factory(),
        ];
    }
}
