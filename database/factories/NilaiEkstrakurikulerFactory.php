<?php

namespace Database\Factories;

use App\Models\Ekstrakurikuler;
use App\Models\NilaiEkstrakurikuler;
use App\Models\Rapor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NilaiEkstrakurikuler>
 */
class NilaiEkstrakurikulerFactory extends Factory
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
            'ekstrakurikuler_id' => Ekstrakurikuler::factory(),
            'nilai' => fake()->randomElement(['A', 'B', 'C']),
        ];
    }
}
