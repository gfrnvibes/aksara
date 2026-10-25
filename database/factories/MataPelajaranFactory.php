<?php

namespace Database\Factories;

use App\Enums\KelompokMapel;
use App\Models\MataPelajaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MataPelajaran>
 */
class MataPelajaranFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->unique()->words(2, true),
            'kelompok' => fake()->randomElement(KelompokMapel::cases()),
            'kkm' => 75,
            'urutan' => fake()->numberBetween(1, 20),
            'is_aktif' => true,
        ];
    }
}
