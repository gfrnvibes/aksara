<?php

use App\Models\Gtk;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\PembagianMengajar;
use App\Models\TahunAjaran;
use Database\Seeders\GuruAtTaufiqSeeder;
use Database\Seeders\MasterDataSeeder;

beforeEach(function () {
    $this->seed(MasterDataSeeder::class);
});

it('seeds the teachers of the timetable with their subjects as this year\'s teaching assignments', function () {
    $gufron = Gtk::factory()->create(['nama' => 'Gufron Nurjalil, S. Kom.']);

    $this->seed(GuruAtTaufiqSeeder::class);
    $this->seed(GuruAtTaufiqSeeder::class);

    $tahunAjaran = TahunAjaran::query()->where('is_aktif', true)->sole();
    $pembagian = PembagianMengajar::query()->where('tahun_ajaran_id', $tahunAjaran->id)->get();

    expect(Gtk::query()->count())->toBe(14)
        ->and(MataPelajaran::query()->whereIn('nama', ['Informatika', 'Tahfidz', 'Bahasa Sunda'])->count())->toBe(3)
        ->and($pembagian->sum('jp_per_minggu'))->toBe(143)
        ->and($pembagian->where('gtk_id', $gufron->id)->sum('jp_per_minggu'))->toBe(17)
        ->and($gufron->refresh()->nama)->toBe('Gufron Nurjalil, S. Kom.');

    foreach (['VII' => 48, 'VIII' => 47, 'IX' => 48] as $kelas => $jp) {
        expect($pembagian->where('kelas_id', Kelas::query()->where('nama', $kelas)->value('id'))->sum('jp_per_minggu'))->toBe($jp);
    }
});
