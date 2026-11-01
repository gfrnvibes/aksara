<?php

namespace Database\Factories;

use App\Enums\Hari;
use App\Models\IsiJadwal;
use App\Models\Jadwal;
use App\Models\PembagianMengajar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IsiJadwal>
 */
class IsiJadwalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'jadwal_id' => Jadwal::factory(),
            'pembagian_mengajar_id' => PembagianMengajar::factory(),
            'kelas_id' => fn (array $atribut): int => (int) PembagianMengajar::query()->whereKey($atribut['pembagian_mengajar_id'])->value('kelas_id'),
            'hari' => Hari::Senin,
            'jp_ke' => 1,
        ];
    }
}
