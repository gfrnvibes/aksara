<?php

namespace Database\Factories;

use App\Enums\JenisGtk;
use App\Enums\JenisKelamin;
use App\Enums\StatusKepegawaian;
use App\Models\Gtk;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gtk>
 */
class GtkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'nip' => fake()->unique()->numerify('19##########0#1###'),
            'nuptk' => fake()->unique()->numerify('################'),
            'jenis_kelamin' => fake()->randomElement(JenisKelamin::cases()),
            'tempat_lahir' => fake()->city(),
            'tanggal_lahir' => fake()->date(max: '2000-01-01'),
            'jenis_gtk' => JenisGtk::Guru,
            'status_kepegawaian' => StatusKepegawaian::TetapYayasan,
            'nomor_hp' => fake()->unique()->numerify('08##########'),
            'is_aktif' => true,
        ];
    }

    /**
     * A GTK with a login account for the teacher panel.
     */
    public function denganAkun(): static
    {
        return $this->afterCreating(function (Gtk $gtk): void {
            $gtk->user()->associate(User::factory()->create(['name' => $gtk->nama, 'email' => null]))->save();
        });
    }
}
