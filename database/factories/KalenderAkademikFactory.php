<?php

namespace Database\Factories;

use App\Enums\JenisAgenda;
use App\Models\KalenderAkademik;
use App\Models\TahunAjaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KalenderAkademik>
 */
class KalenderAkademikFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $mulai = fake()->dateTimeBetween('now', '+2 months');

        return [
            'tahun_ajaran_id' => TahunAjaran::factory(),
            'nama' => fake()->sentence(3),
            'jenis' => JenisAgenda::Kegiatan,
            'tanggal_mulai' => $mulai,
            'tanggal_selesai' => $mulai,
        ];
    }
}
