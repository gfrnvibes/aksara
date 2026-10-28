<?php

namespace Database\Factories;

use App\Models\Ijazah;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ijazah>
 */
class IjazahFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tahun_lulus' => fake()->numberBetween(2005, 2026),
            'nama' => fake()->name(),
            'nis' => fake()->unique()->numerify('######'),
            'nisn' => fake()->unique()->numerify('00########'),
            'nomor_seri' => fake()->unique()->bothify('MTs-##-#######'),
            'tanggal_ijazah' => fake()->date(),
            'berkas' => [],
        ];
    }
}
