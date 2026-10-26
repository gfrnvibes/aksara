<?php

use App\Enums\Semester;
use App\Models\Kelas;
use App\Models\Rapor;
use App\Models\RiwayatKelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;

it('records the class of a new student in the active academic year', function () {
    $tahunAktif = TahunAjaran::factory()->aktif()->create();
    $kelas = Kelas::factory()->create();

    $siswa = Siswa::factory()->for($kelas)->create();

    expect($siswa->riwayatKelas()->pluck('kelas_id', 'tahun_ajaran_id')->all())->toBe([$tahunAktif->id => $kelas->id]);
});

it('corrects the latest year when the current class is changed', function () {
    $tahunAktif = TahunAjaran::factory()->aktif()->create(['nama' => '2025/2026']);
    $tahunBerikutnya = TahunAjaran::factory()->create(['nama' => '2026/2027']);
    [$kelasLama, $kelasPromosi, $kelasKoreksi] = Kelas::factory()->count(3)->create()->all();
    $siswa = Siswa::factory()->for($kelasLama)->create();
    RiwayatKelas::factory()->for($siswa)->for($tahunBerikutnya)->for($kelasPromosi)->create();

    $siswa->update(['kelas_id' => $kelasKoreksi->id]);

    expect($siswa->riwayatKelas()->pluck('kelas_id', 'tahun_ajaran_id')->all())->toEqualCanonicalizing([
        $tahunAktif->id => $kelasLama->id,
        $tahunBerikutnya->id => $kelasKoreksi->id,
    ]);
});

it('fills a missing year of the history from a report card without overwriting a recorded one', function () {
    $tahunLalu = TahunAjaran::factory()->create();
    [$kelasRapor, $kelasTercatat] = Kelas::factory()->count(2)->create()->all();
    $siswa = Siswa::factory()->create();

    Rapor::factory()->for($siswa)->for($tahunLalu)->for($kelasRapor)->create(['semester' => Semester::Ganjil]);

    expect($siswa->riwayatKelas()->sole()->kelas_id)->toBe($kelasRapor->id);

    RiwayatKelas::query()->where('siswa_id', $siswa->id)->update(['kelas_id' => $kelasTercatat->id]);
    Rapor::factory()->for($siswa)->for($tahunLalu)->for($kelasRapor)->create(['semester' => Semester::Genap]);

    expect($siswa->riwayatKelas()->sole()->kelas_id)->toBe($kelasTercatat->id);
});

it('picks the class of the chosen year for a report card, falling back to the current class', function () {
    $tahunLalu = TahunAjaran::factory()->create();
    $tahunTanpaRiwayat = TahunAjaran::factory()->create();
    [$kelasLalu, $kelasSekarang] = Kelas::factory()->count(2)->create()->all();
    $siswa = Siswa::factory()->for($kelasSekarang)->create();
    RiwayatKelas::factory()->for($siswa)->for($tahunLalu)->for($kelasLalu)->create();

    expect($siswa->kelasPadaTahunAjaran($tahunLalu->id))->toBe($kelasLalu->id)
        ->and($siswa->kelasPadaTahunAjaran($tahunTanpaRiwayat->id))->toBe($kelasSekarang->id);
});
