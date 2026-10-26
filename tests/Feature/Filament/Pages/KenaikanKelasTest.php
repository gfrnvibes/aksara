<?php

use App\Enums\KeputusanKenaikan;
use App\Enums\Semester;
use App\Enums\StatusSiswa;
use App\Enums\TindakanKenaikan;
use App\Filament\Pages\KenaikanKelas;
use App\Models\Kelas;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->tahunAsal = TahunAjaran::factory()->aktif()->create(['nama' => '2025/2026']);
    $this->tahunTujuan = TahunAjaran::factory()->create(['nama' => '2026/2027']);
    $this->kelasTujuh = Kelas::factory()->create(['tingkat' => 7, 'nama' => 'VII-A']);
    $this->kelasDelapan = Kelas::factory()->create(['tingkat' => 8, 'nama' => 'VIII-A']);
    Kelas::factory()->create(['tingkat' => 8, 'nama' => 'VIII-B']);

    actingAs(userWithPermissions([KenaikanKelas::IZIN]));
});

/**
 * @return array<int, string> the suggested action per student id
 */
function tindakanPerSiswa(mixed $halaman): array
{
    return collect($halaman->get('data.siswa'))->pluck('tindakan', 'siswa_id')->all();
}

it('is hidden from users without the promotion permission', function () {
    actingAs(User::factory()->create());

    Livewire::test(KenaikanKelas::class)->assertForbidden();
});

it('suggests each action from the even-semester decision and the matching parallel class', function () {
    $naik = Siswa::factory()->for($this->kelasTujuh)->create();
    $tinggal = Siswa::factory()->for($this->kelasTujuh)->create();
    $belumDiputuskan = Siswa::factory()->for($this->kelasTujuh)->create();
    Siswa::factory()->for($this->kelasTujuh)->create(['status' => StatusSiswa::Pindah]);
    Rapor::factory()->for($naik)->for($this->tahunAsal)->for($this->kelasTujuh)->create(['semester' => Semester::Genap, 'keputusan' => KeputusanKenaikan::Naik]);
    Rapor::factory()->for($tinggal)->for($this->tahunAsal)->for($this->kelasTujuh)->create(['semester' => Semester::Genap, 'keputusan' => KeputusanKenaikan::TinggalDiKelas]);

    $halaman = Livewire::test(KenaikanKelas::class)
        ->assertSchemaStateSet(['tahun_ajaran_asal_id' => $this->tahunAsal->id, 'tahun_ajaran_tujuan_id' => $this->tahunTujuan->id])
        ->fillForm(['kelas_asal_id' => $this->kelasTujuh->id])
        ->assertSchemaStateSet(['kelas_naik_id' => $this->kelasDelapan->id, 'kelas_tinggal_id' => $this->kelasTujuh->id]);

    expect(tindakanPerSiswa($halaman))->toEqualCanonicalizing([
        $naik->id => TindakanKenaikan::Naik->value,
        $tinggal->id => TindakanKenaikan::TinggalDiKelas->value,
        $belumDiputuskan->id => TindakanKenaikan::Lewati->value,
    ]);
});

it('promotes the class and leaves processed students out of a second run', function () {
    $naik = Siswa::factory()->for($this->kelasTujuh)->create();
    $tinggal = Siswa::factory()->for($this->kelasTujuh)->create();
    Rapor::factory()->for($naik)->for($this->tahunAsal)->for($this->kelasTujuh)->create(['semester' => Semester::Genap, 'keputusan' => KeputusanKenaikan::Naik]);
    Rapor::factory()->for($tinggal)->for($this->tahunAsal)->for($this->kelasTujuh)->create(['semester' => Semester::Genap, 'keputusan' => KeputusanKenaikan::TinggalDiKelas]);

    $halaman = Livewire::test(KenaikanKelas::class)
        ->fillForm(['kelas_asal_id' => $this->kelasTujuh->id])
        ->callAction('prosesKenaikan')
        ->assertNotified('Kenaikan kelas selesai diproses');

    expect($naik->fresh()->kelas_id)->toBe($this->kelasDelapan->id)
        ->and($tinggal->fresh()->riwayatKelas()->where('tahun_ajaran_id', $this->tahunTujuan->id)->value('kelas_id'))->toBe($this->kelasTujuh->id)
        ->and(tindakanPerSiswa($halaman))->toBe([$tinggal->id => TindakanKenaikan::Lewati->value]);
});

it('asks for the destination class before promoting anyone', function () {
    $siswa = Siswa::factory()->for($this->kelasTujuh)->create();
    Rapor::factory()->for($siswa)->for($this->tahunAsal)->for($this->kelasTujuh)->create(['semester' => Semester::Genap, 'keputusan' => KeputusanKenaikan::Naik]);
    Kelas::factory()->create(['tingkat' => 7, 'nama' => 'VII-C']);

    Livewire::test(KenaikanKelas::class)
        ->fillForm(['kelas_asal_id' => $this->kelasTujuh->id, 'kelas_naik_id' => null])
        ->callAction('prosesKenaikan')
        ->assertHasErrors(['data.kelas_naik_id' => 'required']);

    expect($siswa->fresh()->kelas_id)->toBe($this->kelasTujuh->id);
});

it('offers graduation instead of promotion for the final grade', function () {
    $kelasSembilan = Kelas::factory()->create(['tingkat' => 9, 'nama' => 'IX-A']);
    $lulusan = Siswa::factory()->for($kelasSembilan)->create();
    Rapor::factory()->for($lulusan)->for($this->tahunAsal)->for($kelasSembilan)->create(['semester' => Semester::Genap, 'keputusan' => KeputusanKenaikan::Lulus]);

    $halaman = Livewire::test(KenaikanKelas::class)
        ->fillForm(['kelas_asal_id' => $kelasSembilan->id])
        ->assertSchemaComponentHidden('kelas_naik_id');

    expect(tindakanPerSiswa($halaman))->toBe([$lulusan->id => TindakanKenaikan::Lulus->value]);

    $halaman->callAction('prosesKenaikan');

    expect($lulusan->fresh()->status)->toBe(StatusSiswa::Lulus);
});
