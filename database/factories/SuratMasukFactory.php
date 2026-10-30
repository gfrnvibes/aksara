<?php

namespace Database\Factories;

use App\Enums\SifatSurat;
use App\Enums\StatusSuratMasuk;
use App\Models\SuratMasuk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SuratMasuk>
 */
class SuratMasukFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tanggal_diterima' => fake()->dateTimeBetween('-1 month'),
            'nomor_surat' => fake()->numerify('B-###/Kw.10/PP.00/2026'),
            'tanggal_surat' => fake()->dateTimeBetween('-2 months', '-1 month'),
            'pengirim' => 'Kantor Kementerian Agama Kabupaten Garut',
            'perihal' => 'Pemberitahuan Kegiatan',
            'sifat' => SifatSurat::Biasa,
            'status' => StatusSuratMasuk::Baru,
            'berkas' => [],
        ];
    }
}
