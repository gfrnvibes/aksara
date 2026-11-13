<?php

namespace Database\Factories;

use App\Enums\JenisSoal;
use App\Enums\LevelKognitif;
use App\Models\MataPelajaran;
use App\Models\Soal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Soal>
 */
class SoalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'mata_pelajaran_id' => MataPelajaran::factory(),
            'tingkat' => 7,
            'jenis' => JenisSoal::PilihanGanda,
            'materi' => fake()->words(3, true),
            'level_kognitif' => LevelKognitif::C2,
            'pertanyaan' => '<p>'.fake()->sentence().'?</p>',
            'opsi' => [
                ['teks' => 'Jawaban benar', 'gambar' => null, 'benar' => true],
                ['teks' => 'Pengecoh 1', 'gambar' => null, 'benar' => false],
                ['teks' => 'Pengecoh 2', 'gambar' => null, 'benar' => false],
                ['teks' => 'Pengecoh 3', 'gambar' => null, 'benar' => false],
            ],
            'is_siap' => true,
        ];
    }
}
