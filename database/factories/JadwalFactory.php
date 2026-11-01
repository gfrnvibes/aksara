<?php

namespace Database\Factories;

use App\Enums\Semester;
use App\Enums\StatusJadwal;
use App\Models\Jadwal;
use App\Models\TahunAjaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Jadwal>
 */
class JadwalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tahun_ajaran_id' => TahunAjaran::factory(),
            'semester' => Semester::Ganjil,
            'status' => StatusJadwal::Draf,
        ];
    }
}
