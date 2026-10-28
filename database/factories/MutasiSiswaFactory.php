<?php

namespace Database\Factories;

use App\Enums\JenisMutasi;
use App\Models\MutasiSiswa;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MutasiSiswa>
 */
class MutasiSiswaFactory extends Factory
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
            'jenis' => JenisMutasi::Pindah,
            'tanggal' => fake()->dateTimeBetween('-1 year'),
            'sekolah' => 'MTs '.fake()->city(),
            'alasan' => 'Mengikuti orang tua',
            'nomor_surat' => fake()->numerify('B-###/Mts.10.05/PP.00/'.now()->year),
        ];
    }
}
