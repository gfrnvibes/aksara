<?php

namespace Database\Factories;

use App\Enums\Agama;
use App\Enums\JenisOrangTua;
use App\Models\OrangTua;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrangTua>
 */
class OrangTuaFactory extends Factory
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
            'jenis' => JenisOrangTua::Ayah,
            'nama' => fake()->name('male'),
            'agama' => Agama::Islam,
            'pekerjaan' => fake()->jobTitle(),
            'masih_hidup' => true,
        ];
    }
}
