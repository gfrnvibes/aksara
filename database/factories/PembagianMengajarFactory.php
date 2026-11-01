<?php

namespace Database\Factories;

use App\Models\Gtk;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\PembagianMengajar;
use App\Models\TahunAjaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PembagianMengajar>
 */
class PembagianMengajarFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tahun_ajaran_id' => TahunAjaran::factory(),
            'kelas_id' => Kelas::factory(),
            'mata_pelajaran_id' => MataPelajaran::factory(),
            'gtk_id' => Gtk::factory(),
            'jp_per_minggu' => 2,
        ];
    }
}
