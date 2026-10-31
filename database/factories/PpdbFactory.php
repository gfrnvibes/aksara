<?php

namespace Database\Factories;

use App\Enums\Agama;
use App\Enums\JenisKelamin;
use App\Enums\StatusPpdb;
use App\Models\Ppdb;
use App\Models\TahunAjaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ppdb>
 */
class PpdbFactory extends Factory
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
            'status' => StatusPpdb::Menunggu,
            'nama_lengkap' => fake()->name(),
            'nisn' => fake()->unique()->numerify('##########'),
            'nik' => fake()->unique()->numerify('3205############'),
            'tempat_lahir' => 'Garut',
            'tanggal_lahir' => fake()->dateTimeBetween('-13 years', '-12 years'),
            'jenis_kelamin' => fake()->randomElement(JenisKelamin::cases()),
            'agama' => Agama::Islam,
            'asal_sekolah' => 'SD Negeri 1 Cisurupan',
            'alamat' => fake()->address(),
            'nomor_telepon' => fake()->numerify('08##########'),
            'nama_ayah_kandung' => fake()->name('male'),
            'nama_ibu_kandung' => fake()->name('female'),
            'berkas' => [],
        ];
    }

    public function status(StatusPpdb $status): static
    {
        return $this->state(['status' => $status]);
    }
}
