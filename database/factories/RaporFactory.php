<?php

namespace Database\Factories;

use App\Enums\Semester;
use App\Models\Kelas;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rapor>
 */
class RaporFactory extends Factory
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
            'semester' => Semester::Ganjil,
            'sakit' => fake()->numberBetween(0, 3),
            'izin' => fake()->numberBetween(0, 3),
            'tanpa_keterangan' => 0,
        ];
    }
}
