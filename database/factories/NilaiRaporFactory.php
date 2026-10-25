<?php

namespace Database\Factories;

use App\Models\MataPelajaran;
use App\Models\NilaiRapor;
use App\Models\Rapor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NilaiRapor>
 */
class NilaiRaporFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rapor_id' => Rapor::factory(),
            'mata_pelajaran_id' => MataPelajaran::factory(),
            'kkm' => 75,
            'nilai_pengetahuan' => fake()->numberBetween(70, 100),
            'nilai_keterampilan' => fake()->numberBetween(70, 100),
            'sikap' => fake()->randomElement(['SB', 'B']),
        ];
    }
}
