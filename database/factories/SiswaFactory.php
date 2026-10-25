<?php

namespace Database\Factories;

use App\Enums\Agama;
use App\Enums\JenisKelamin;
use App\Enums\StatusSiswa;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Siswa>
 */
class SiswaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $jenisKelamin = fake()->randomElement(JenisKelamin::cases());

        return [
            'nis' => fake()->unique()->numerify('######'),
            'nisn' => fake()->unique()->numerify('00########'),
            'status' => StatusSiswa::Aktif,
            'nama_lengkap' => fake()->name($jenisKelamin === JenisKelamin::LakiLaki ? 'male' : 'female'),
            'jenis_kelamin' => $jenisKelamin,
            'tempat_lahir' => fake()->city(),
            'tanggal_lahir' => fake()->dateTimeBetween('-15 years', '-12 years'),
            'agama' => Agama::Islam,
            'kewarganegaraan' => 'WNI',
            'anak_ke' => fake()->numberBetween(1, 4),
            'alamat' => fake()->address(),
            'diterima_di_tingkat' => 7,
            'tanggal_diterima' => fake()->dateTimeBetween('-2 years'),
        ];
    }
}
