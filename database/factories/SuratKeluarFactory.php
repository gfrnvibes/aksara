<?php

namespace Database\Factories;

use App\Enums\JenisSuratKeluar;
use App\Enums\SifatSurat;
use App\Models\SuratKeluar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SuratKeluar>
 */
class SuratKeluarFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'jenis' => JenisSuratKeluar::Undangan,
            'sifat' => SifatSurat::Biasa,
            'tanggal' => fake()->dateTimeBetween('-1 month'),
            'perihal' => 'Undangan Rapat Wali Murid',
            'tujuan' => 'Bapak/Ibu Orang Tua/Wali Siswa',
            'isi' => '<p>Dengan hormat, kami mengundang Bapak/Ibu untuk hadir pada rapat wali murid.</p>',
            'penanda_tangan_id' => null,
        ];
    }
}
