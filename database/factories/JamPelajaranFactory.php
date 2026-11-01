<?php

namespace Database\Factories;

use App\Enums\Hari;
use App\Enums\JenisJamPelajaran;
use App\Models\JamPelajaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JamPelajaran>
 */
class JamPelajaranFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hari' => Hari::Senin,
            'mulai' => '07:30',
            'selesai' => '08:05',
            'jenis' => JenisJamPelajaran::Pelajaran,
        ];
    }
}
