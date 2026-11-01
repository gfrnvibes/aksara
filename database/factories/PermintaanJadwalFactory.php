<?php

namespace Database\Factories;

use App\Enums\JenisPermintaanJadwal;
use App\Enums\SifatPermintaanJadwal;
use App\Models\Gtk;
use App\Models\PermintaanJadwal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PermintaanJadwal>
 */
class PermintaanJadwalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gtk_id' => Gtk::factory(),
            'jenis' => JenisPermintaanJadwal::TidakBisa,
            'sifat' => SifatPermintaanJadwal::Wajib,
            'hari' => null,
            'jp_mulai' => null,
            'jp_selesai' => null,
            'is_aktif' => true,
        ];
    }
}
