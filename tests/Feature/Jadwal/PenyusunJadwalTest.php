<?php

use App\Enums\Hari;
use App\Enums\JenisPermintaanJadwal;
use App\Enums\SifatPermintaanJadwal;
use App\Models\Gtk;
use App\Models\IsiJadwal;
use App\Models\Jadwal;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\PembagianMengajar;
use App\Models\PermintaanJadwal;
use App\Models\TahunAjaran;
use App\Services\Jadwal\PemeriksaJadwal;
use App\Services\Jadwal\PenyusunJadwal;
use App\Services\Jadwal\PolaJam;

beforeEach(function () {
    JamPelajaran::isiBawaan();

    $this->tahunAjaran = TahunAjaran::factory()->aktif()->create();
    $this->jadwal = Jadwal::factory()->for($this->tahunAjaran)->create();
});

/**
 * The teaching assignments of MTs At-Taufiq's 2026/2027 odd-semester timetable: teacher => [subject => JP for
 * VII, VIII, IX]. Classes VII and IX fill all 48 periods of the week, VIII 47.
 *
 * @return array<string, Kelas>
 */
function pembagianAtTaufiq(TahunAjaran $tahunAjaran): array
{
    $kelas = [
        'VII' => Kelas::factory()->create(['tingkat' => 7, 'nama' => 'VII']),
        'VIII' => Kelas::factory()->create(['tingkat' => 8, 'nama' => 'VIII']),
        'IX' => Kelas::factory()->create(['tingkat' => 9, 'nama' => 'IX']),
    ];

    $guru = [
        'Sofwan' => ['Al-Qur\'an Hadits' => [2, 2, 2], 'SKI' => [0, 2, 2]],
        'Enur' => ['Fikih' => [2, 2, 2]],
        'Dodi' => ['Aqidah Akhlak' => [2, 2, 2], 'SKI' => [2, 0, 0], 'Bahasa Indonesia' => [0, 0, 6]],
        'Dede' => ['Bahasa Inggris' => [4, 4, 4]],
        'Ahmad W' => ['PKn' => [3, 3, 3]],
        'Irma' => ['IPA' => [0, 5, 5]],
        'Abdul' => ['Matematika' => [5, 5, 5]],
        'Evi' => ['IPS' => [4, 3, 4]],
        'Akoh' => ['Tahfidz' => [2, 2, 2]],
        'Mu\'min' => ['Seni Budaya' => [2, 2, 2]],
        'Ahmad N' => ['Informatika' => [2, 2, 2]],
        'Ade' => ['Bahasa Arab' => [3, 3, 3], 'Bahasa Indonesia' => [0, 6, 0]],
        'Gufron' => ['Bahasa Sunda' => [2, 2, 2], 'Bahasa Indonesia' => [6, 0, 0], 'IPA' => [5, 0, 0]],
        'Asep' => ['Penjaskes' => [2, 2, 2]],
    ];

    $mapel = [];

    foreach ($guru as $nama => $daftarMapel) {
        $gtk = Gtk::factory()->create(['nama' => $nama]);

        foreach ($daftarMapel as $namaMapel => $jp) {
            $mapel[$namaMapel] ??= MataPelajaran::factory()->create(['nama' => $namaMapel, 'urutan' => count($mapel) + 1]);

            foreach (array_values($kelas) as $i => $satuKelas) {
                if ($jp[$i] > 0) {
                    PembagianMengajar::factory()->for($tahunAjaran)->for($satuKelas)->for($mapel[$namaMapel])->for($gtk)->create(['jp_per_minggu' => $jp[$i]]);
                }
            }
        }
    }

    return $kelas;
}

function penyusun(): PenyusunJadwal
{
    return new PenyusunJadwal(PolaJam::muat(), benih: 7);
}

it('numbers the default bell schedule into 48 lesson periods a week', function () {
    $pola = PolaJam::muat();

    expect($pola->jumlahJp())->toBe(48)
        ->and($pola->jp(Hari::Senin))->toBe([1, 2, 3, 4, 5, 6, 7, 8])
        ->and($pola->jp(Hari::Jumat))->toBe([1, 2, 3, 4, 5])
        ->and($pola->satuSesi(Hari::Senin, 3, 4))->toBeTrue()
        ->and($pola->satuSesi(Hari::Senin, 4, 5))->toBeFalse()
        ->and($pola->satuSesi(Hari::Senin, 7, 8))->toBeFalse();
});

it('renumbers the lesson periods of a day when a row is added', function () {
    JamPelajaran::query()->create(['hari' => Hari::Senin, 'mulai' => '12:50', 'selesai' => '13:25', 'jenis' => 'pelajaran']);
    JamPelajaran::query()->where('hari', Hari::Senin)->where('mulai', '07:30:00')->first()->delete();

    expect(PolaJam::muat()->jp(Hari::Senin))->toBe([1, 2, 3, 4, 5, 6, 7, 8]);
});

it('builds the full timetable of MTs At-Taufiq without clashes and with every period placed', function () {
    pembagianAtTaufiq($this->tahunAjaran);

    $hasil = penyusun()->susun($this->jadwal);

    expect($hasil['berhasil'])->toBeTrue()
        ->and($this->jadwal->isi()->count())->toBe(143);

    $galat = collect((new PemeriksaJadwal(PolaJam::muat()))->periksa($this->jadwal))->where('tingkat', PemeriksaJadwal::GALAT);
    $peringatan = collect((new PemeriksaJadwal(PolaJam::muat()))->periksa($this->jadwal))->where('tingkat', PemeriksaJadwal::PERINGATAN);

    expect($galat)->toBeEmpty()
        ->and($peringatan)->toBeEmpty();
});

it('never breaks a required wish', function () {
    pembagianAtTaufiq($this->tahunAjaran);
    $dede = Gtk::query()->where('nama', 'Dede')->sole();
    PermintaanJadwal::factory()->for($dede)->create([
        'jenis' => JenisPermintaanJadwal::TidakBisa,
        'sifat' => SifatPermintaanJadwal::Wajib,
        'hari' => [Hari::Jumat->value, Hari::Sabtu->value],
    ]);

    expect(penyusun()->susun($this->jadwal)['berhasil'])->toBeTrue();

    expect(IsiJadwal::query()
        ->whereHas('pembagianMengajar', fn ($query) => $query->where('gtk_id', $dede->id))
        ->whereIn('hari', [Hari::Jumat->value, Hari::Sabtu->value])
        ->count())->toBe(0);
});

it('follows a non-required wish when the timetable allows', function () {
    $kelas = Kelas::factory()->create();
    $guru = Gtk::factory()->create();
    PembagianMengajar::factory()->for($this->tahunAjaran)->for($kelas)->for($guru)->create(['jp_per_minggu' => 4]);
    PermintaanJadwal::factory()->for($guru)->create([
        'jenis' => JenisPermintaanJadwal::Ingin,
        'sifat' => SifatPermintaanJadwal::Diusahakan,
        'jp_mulai' => 1,
        'jp_selesai' => 4,
    ]);

    expect(penyusun()->susun($this->jadwal)['berhasil'])->toBeTrue()
        ->and($this->jadwal->isi()->pluck('jp_ke')->max())->toBeLessThanOrEqual(4);
});

it('keeps double lessons within one session and spreads a subject over the week', function () {
    $kelas = Kelas::factory()->create();
    PembagianMengajar::factory()->for($this->tahunAjaran)->for($kelas)->create(['jp_per_minggu' => 6]);

    penyusun()->susun($this->jadwal);
    $isi = $this->jadwal->isi()->orderBy('hari')->orderBy('jp_ke')->get()->groupBy(fn (IsiJadwal $sel): int => $sel->hari->value);
    $pola = PolaJam::muat();

    expect($isi)->toHaveCount(3);

    foreach ($isi as $hari => $sel) {
        expect($sel)->toHaveCount(2)
            ->and($sel[1]->jp_ke - $sel[0]->jp_ke)->toBe(1)
            ->and($pola->satuSesi(Hari::from($hari), $sel[0]->jp_ke, $sel[1]->jp_ke))->toBeTrue();
    }
});

it('keeps locked periods where they are', function () {
    $kelas = Kelas::factory()->create();
    $tugas = PembagianMengajar::factory()->for($this->tahunAjaran)->for($kelas)->create(['jp_per_minggu' => 3]);
    IsiJadwal::factory()->for($this->jadwal)->for($tugas)->create(['kelas_id' => $kelas->id, 'hari' => Hari::Sabtu, 'jp_ke' => 8, 'is_terkunci' => true]);
    IsiJadwal::factory()->for($this->jadwal)->for($tugas)->create(['kelas_id' => $kelas->id, 'hari' => Hari::Senin, 'jp_ke' => 1, 'is_terkunci' => false]);

    expect(penyusun()->susun($this->jadwal)['berhasil'])->toBeTrue()
        ->and($this->jadwal->isi()->count())->toBe(3)
        ->and($this->jadwal->isi()->where('hari', Hari::Sabtu)->where('jp_ke', 8)->where('is_terkunci', true)->exists())->toBeTrue();
});

it('explains why a timetable cannot fit and leaves the current one untouched', function () {
    $kelas = Kelas::factory()->create(['nama' => 'VII-A']);
    $guru = Gtk::factory()->create(['nama' => 'Pak Ahmad']);
    PembagianMengajar::factory()->for($this->tahunAjaran)->for($kelas)->for($guru)->create(['jp_per_minggu' => 10]);
    PermintaanJadwal::factory()->for($guru)->create(['hari' => [1, 2, 3, 4, 5]]);
    $lama = IsiJadwal::factory()->for($this->jadwal)->create();

    $hasil = penyusun()->susun($this->jadwal);

    expect($hasil['berhasil'])->toBeFalse()
        ->and($hasil['laporan'][0]['pesan'])->toContain('Pak Ahmad harus mengajar 10 JP')
        ->and($this->jadwal->isi()->sole()->id)->toBe($lama->id);
});

it('asks for the bell schedule and teaching assignments first', function () {
    expect(penyusun()->susun($this->jadwal)['laporan'][0]['pesan'])->toContain('Pembagian Mengajar');

    JamPelajaran::query()->delete();

    expect((new PenyusunJadwal(PolaJam::muat()))->susun($this->jadwal)['laporan'][0]['pesan'])->toContain('Jam Pelajaran belum diatur');
});

it('reports clashes and missing periods when checking a timetable', function () {
    $guru = Gtk::factory()->create(['nama' => 'Bu Siti']);
    [$kelasA, $kelasB] = Kelas::factory()->count(2)->create();
    $tugasA = PembagianMengajar::factory()->for($this->tahunAjaran)->for($kelasA)->for($guru)->create(['jp_per_minggu' => 2]);
    $tugasB = PembagianMengajar::factory()->for($this->tahunAjaran)->for($kelasB)->for($guru)->create(['jp_per_minggu' => 1]);
    IsiJadwal::factory()->for($this->jadwal)->for($tugasA)->create(['kelas_id' => $kelasA->id]);
    IsiJadwal::factory()->for($this->jadwal)->for($tugasB)->create(['kelas_id' => $kelasB->id]);

    $pesan = collect((new PemeriksaJadwal(PolaJam::muat()))->periksa($this->jadwal))->pluck('pesan');

    expect($pesan->first(fn (string $teks): bool => str_starts_with($teks, 'Bentrok: Bu Siti')))->not->toBeNull()
        ->and($pesan->first(fn (string $teks): bool => str_contains($teks, 'terpasang 1 dari 2 JP')))->not->toBeNull();
});
