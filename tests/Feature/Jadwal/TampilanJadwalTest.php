<?php

use App\Models\Gtk;
use App\Models\Jadwal;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\PembagianMengajar;
use App\Models\TahunAjaran;
use App\Services\Jadwal\TampilanJadwal;

it('numbers teachers by their timetable code and letters their subjects in the order they were assigned', function () {
    JamPelajaran::isiBawaan();
    $tahunAjaran = TahunAjaran::factory()->aktif()->create();
    $kelas = Kelas::factory()->create();
    $gufron = Gtk::factory()->create(['nama' => 'Gufron', 'nomor_kode' => 13]);
    $sofwan = Gtk::factory()->create(['nama' => 'Sofwan', 'nomor_kode' => 1]);
    $baru = Gtk::factory()->create(['nama' => 'Guru Baru']);

    $tugas = fn (Gtk $gtk, int $urutan): PembagianMengajar => PembagianMengajar::factory()->for($tahunAjaran)->for($kelas)->for($gtk)
        ->for(MataPelajaran::factory()->create(['urutan' => $urutan]))
        ->create();
    $sunda = $tugas($gufron, 9);
    $indonesia = $tugas($gufron, 1);
    $quran = $tugas($sofwan, 5);
    $lain = $tugas($baru, 2);

    $tampilan = TampilanJadwal::untuk(Jadwal::factory()->for($tahunAjaran)->create());

    expect($tampilan->kode[$quran->id]['kode'])->toBe('01')
        ->and($tampilan->kode[$sunda->id]['kode'])->toBe('13 A')
        ->and($tampilan->kode[$indonesia->id]['kode'])->toBe('13 B')
        ->and($tampilan->kode[$lain->id]['kode'])->toBe('14')
        ->and(array_column($tampilan->legenda, 'guru'))->toBe(['Sofwan', 'Gufron', 'Guru Baru']);
});
