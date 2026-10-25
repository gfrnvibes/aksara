<?php

namespace Database\Factories;

use App\Models\TahunAjaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TahunAjaran>
 */
class TahunAjaranFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tahunMulai = fake()->unique()->numberBetween(2000, 2099);

        return [
            'nama' => $tahunMulai.'/'.($tahunMulai + 1),
            'is_aktif' => false,
        ];
    }

    /**
     * Indicate that the academic year is the active one.
     */
    public function aktif(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_aktif' => true,
        ]);
    }
}
