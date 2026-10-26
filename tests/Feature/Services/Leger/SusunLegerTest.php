<?php

use App\Enums\Semester;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\NilaiRapor;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Services\Leger\SusunLeger;

beforeEach(function () {
    $this->tahunAjaran = TahunAjaran::factory()->aktif()->create();
    $this->kelas = Kelas::factory()->create();
    $this->matematika = MataPelajaran::factory()->create(['kelompok' => 'A', 'urutan' => 1]);
    $this->ipa = MataPelajaran::factory()->create(['kelompok' => 'A', 'urutan' => 2]);
});

/**
 * Give the student odd-semester grades: subject id => [knowledge, skill].
 *
 * @param  array<int, array{0: int|null, 1: int|null}>  $nilai
 */
function beriNilai(Siswa $siswa, array $nilai, Semester $semester = Semester::Ganjil): void
{
    $rapor = Rapor::factory()->for($siswa)->for(test()->tahunAjaran)->for(test()->kelas)->create(['semester' => $semester]);

    foreach ($nilai as $mataPelajaranId => [$pengetahuan, $keterampilan]) {
        NilaiRapor::factory()->for($rapor)->create([
            'mata_pelajaran_id' => $mataPelajaranId,
            'nilai_pengetahuan' => $pengetahuan,
            'nilai_keterampilan' => $keterampilan,
        ]);
    }
}

it('ranks students by their average, sharing a rank on equal averages', function () {
    $ahmad = Siswa::factory()->for($this->kelas)->create(['nama_lengkap' => 'Ahmad']);
    $budi = Siswa::factory()->for($this->kelas)->create(['nama_lengkap' => 'Budi']);
    $citra = Siswa::factory()->for($this->kelas)->create(['nama_lengkap' => 'Citra']);
    $dewi = Siswa::factory()->for($this->kelas)->create(['nama_lengkap' => 'Dewi']);
    beriNilai($ahmad, [$this->matematika->id => [80, 80], $this->ipa->id => [80, 80]]);
    beriNilai($budi, [$this->matematika->id => [90, 90], $this->ipa->id => [90, 90]]);
    beriNilai($citra, [$this->matematika->id => [90, 90], $this->ipa->id => [90, 90]]);

    $leger = app(SusunLeger::class)->untuk($this->kelas, $this->tahunAjaran, Semester::Ganjil);

    expect(array_map(fn (array $baris): array => [$baris['siswa']->nama_lengkap, $baris['jumlah'], $baris['rata_rata'], $baris['peringkat']], $leger->baris))
        ->toBe([
            ['Ahmad', 320, 80.0, 3],
            ['Budi', 360, 90.0, 1],
            ['Citra', 360, 90.0, 1],
            ['Dewi', 0, null, null],
        ]);
});

it('averages only the scores that were filled in', function () {
    $siswa = Siswa::factory()->for($this->kelas)->create();
    beriNilai($siswa, [$this->matematika->id => [70, null], $this->ipa->id => [90, null]]);

    $leger = app(SusunLeger::class)->untuk($this->kelas, $this->tahunAjaran, Semester::Ganjil);

    expect($leger->baris[0]['rata_rata'])->toBe(80.0)
        ->and($leger->rataRataMapel()[$this->matematika->id])->toBe(['p' => 70.0, 'k' => null]);
});

it('shows only the chosen semester and the subjects in curriculum order', function () {
    $siswa = Siswa::factory()->for($this->kelas)->create();
    $mapelLama = MataPelajaran::factory()->create(['is_aktif' => false, 'kelompok' => 'B', 'urutan' => 1]);
    MataPelajaran::factory()->create(['is_aktif' => false]);
    beriNilai($siswa, [$mapelLama->id => [75, 75]]);
    beriNilai($siswa, [$this->matematika->id => [99, 99]], Semester::Genap);

    $leger = app(SusunLeger::class)->untuk($this->kelas, $this->tahunAjaran, Semester::Ganjil);

    expect($leger->mataPelajaran->modelKeys())->toBe([$this->matematika->id, $this->ipa->id, $mapelLama->id])
        ->and($leger->baris[0]['nilai'][$this->matematika->id]['p'])->toBeNull()
        ->and($leger->baris[0]['rata_rata'])->toBe(75.0);
});
