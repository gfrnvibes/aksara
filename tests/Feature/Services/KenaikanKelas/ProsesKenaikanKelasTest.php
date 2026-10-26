<?php

use App\Enums\KeputusanKenaikan;
use App\Enums\Semester;
use App\Enums\StatusSiswa;
use App\Enums\TindakanKenaikan;
use App\Models\Kelas;
use App\Models\Rapor;
use App\Models\RiwayatKelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Services\KenaikanKelas\ProsesKenaikanKelas;

beforeEach(function () {
    $this->tahunAsal = TahunAjaran::factory()->aktif()->create(['nama' => '2025/2026']);
    $this->tahunTujuan = TahunAjaran::factory()->create(['nama' => '2026/2027']);
    $this->kelasTujuh = Kelas::factory()->create(['tingkat' => 7, 'nama' => 'VII-A']);
    $this->kelasDelapan = Kelas::factory()->create(['tingkat' => 8, 'nama' => 'VIII-A']);
    $this->kelasSembilan = Kelas::factory()->create(['tingkat' => 9, 'nama' => 'IX-A']);
});

/**
 * @param  array<int, TindakanKenaikan>  $tindakan
 * @return array<string, int>
 */
function prosesKenaikan(Kelas $kelasAsal, ?Kelas $kelasNaik, array $tindakan): array
{
    return app(ProsesKenaikanKelas::class)->proses(test()->tahunAsal, test()->tahunTujuan, $kelasAsal, $kelasNaik, $kelasAsal, $tindakan);
}

it('moves promoted and retained students into the new year and keeps the old class in the history', function () {
    $naik = Siswa::factory()->for($this->kelasTujuh)->create();
    $tinggal = Siswa::factory()->for($this->kelasTujuh)->create();
    $dilewati = Siswa::factory()->for($this->kelasTujuh)->create();

    $jumlah = prosesKenaikan($this->kelasTujuh, $this->kelasDelapan, [
        $naik->id => TindakanKenaikan::Naik,
        $tinggal->id => TindakanKenaikan::TinggalDiKelas,
        $dilewati->id => TindakanKenaikan::Lewati,
    ]);

    expect($jumlah)->toMatchArray(['naik' => 1, 'tinggal' => 1, 'lewati' => 1, 'lulus' => 0])
        ->and($naik->fresh()->kelas_id)->toBe($this->kelasDelapan->id)
        ->and($tinggal->fresh()->kelas_id)->toBe($this->kelasTujuh->id)
        ->and($dilewati->fresh()->kelas_id)->toBe($this->kelasTujuh->id)
        ->and($naik->riwayatKelas()->pluck('kelas_id', 'tahun_ajaran_id')->all())->toEqualCanonicalizing([
            $this->tahunAsal->id => $this->kelasTujuh->id,
            $this->tahunTujuan->id => $this->kelasDelapan->id,
        ])
        ->and($tinggal->riwayatKelas()->where('tahun_ajaran_id', $this->tahunTujuan->id)->value('kelas_id'))->toBe($this->kelasTujuh->id)
        ->and($dilewati->riwayatKelas()->where('tahun_ajaran_id', $this->tahunTujuan->id)->exists())->toBeFalse();
});

it('turns final-grade graduates into alumni dated by their promotion decision', function () {
    $lulusan = Siswa::factory()->for($this->kelasSembilan)->create(['tanggal_tamat' => null]);
    Rapor::factory()->for($lulusan)->for($this->tahunAsal)->for($this->kelasSembilan)->create([
        'semester' => Semester::Genap,
        'keputusan' => KeputusanKenaikan::Lulus,
        'tanggal_keputusan' => '2026-06-20',
    ]);

    prosesKenaikan($this->kelasSembilan, null, [$lulusan->id => TindakanKenaikan::Lulus]);

    $lulusan->refresh();

    expect($lulusan->status)->toBe(StatusSiswa::Lulus)
        ->and($lulusan->kelas_id)->toBeNull()
        ->and($lulusan->tanggal_tamat->toDateString())->toBe('2026-06-20')
        ->and($lulusan->riwayatKelas()->pluck('kelas_id', 'tahun_ajaran_id')->all())->toBe([$this->tahunAsal->id => $this->kelasSembilan->id]);
});

it('ignores students who are no longer active in the class being processed', function () {
    $pindahKelas = Siswa::factory()->for($this->kelasDelapan)->create();
    $keluar = Siswa::factory()->for($this->kelasTujuh)->create(['status' => StatusSiswa::Keluar]);

    $jumlah = prosesKenaikan($this->kelasTujuh, $this->kelasDelapan, [
        $pindahKelas->id => TindakanKenaikan::Naik,
        $keluar->id => TindakanKenaikan::Naik,
    ]);

    expect($jumlah['naik'])->toBe(0)
        ->and($pindahKelas->fresh()->kelas_id)->toBe($this->kelasDelapan->id)
        ->and($keluar->fresh()->kelas_id)->toBe($this->kelasTujuh->id);
});

it('refuses combinations that do not fit the classes or years', function (string $kasus, string $pesan) {
    $proses = match ($kasus) {
        'tahun' => fn () => app(ProsesKenaikanKelas::class)->proses(
            $this->tahunTujuan,
            $this->tahunAsal,
            $this->kelasTujuh,
            $this->kelasDelapan,
            null,
            [Siswa::factory()->for($this->kelasTujuh)->create()->id => TindakanKenaikan::Naik],
        ),
        'lompat' => fn () => prosesKenaikan($this->kelasTujuh, $this->kelasSembilan, [Siswa::factory()->for($this->kelasTujuh)->create()->id => TindakanKenaikan::Naik]),
        'akhir-naik' => fn () => prosesKenaikan($this->kelasSembilan, $this->kelasDelapan, [Siswa::factory()->for($this->kelasSembilan)->create()->id => TindakanKenaikan::Naik]),
        'lulus-dini' => fn () => prosesKenaikan($this->kelasTujuh, null, [Siswa::factory()->for($this->kelasTujuh)->create()->id => TindakanKenaikan::Lulus]),
    };

    expect($proses)->toThrow(DomainException::class, $pesan)
        ->and(RiwayatKelas::query()->where('tahun_ajaran_id', $this->tahunTujuan->id)->exists())->toBeFalse();
})->with([
    'tahun tujuan tidak sesudah tahun asal' => ['tahun', 'Tahun ajaran tujuan harus sesudah 2026/2027.'],
    'kelas naik tidak satu tingkat di atas' => ['lompat', 'Pilih kelas tujuan satu tingkat di atas kelas asal'],
    'kelas akhir naik kelas' => ['akhir-naik', 'tidak bisa naik kelas'],
    'meluluskan siswa bukan tingkat akhir' => ['lulus-dini', 'Hanya siswa tingkat akhir yang bisa diluluskan.'],
]);
