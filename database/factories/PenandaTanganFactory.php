<?php

namespace Database\Factories;

use App\Models\PenandaTangan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PenandaTangan>
 */
class PenandaTanganFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'jabatan' => 'Wakil Kepala Madrasah Bidang Kesiswaan',
            'nip' => fake()->numerify('19##########0#1###'),
            'urutan' => 1,
            'is_aktif' => true,
        ];
    }
}
