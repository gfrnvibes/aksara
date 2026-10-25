<?php

use App\Enums\Semester;
use App\Filament\Resources\Rapors\Pages\CreateRapor;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    actingAs(userWithPermissions(['ViewAny:Rapor', 'View:Rapor', 'Create:Rapor', 'Update:Rapor']));
});

it('prefills every active subject with its KKM', function () {
    $matematika = MataPelajaran::factory()->create(['kkm' => 70]);
    MataPelajaran::factory()->create(['is_aktif' => false]);

    $nilaiRapor = array_values(Livewire::test(CreateRapor::class)->get('data.nilaiRapor'));

    expect($nilaiRapor)->toHaveCount(1)
        ->and($nilaiRapor[0]['mata_pelajaran_id'])->toBe($matematika->id)
        ->and($nilaiRapor[0]['kkm'])->toBe(70);
});

it('stores the grades of a semester with computed letter grades', function () {
    $siswa = Siswa::factory()->create();
    $tahunAjaran = TahunAjaran::factory()->create();
    $kelas = Kelas::factory()->create();
    $matematika = MataPelajaran::factory()->create(['kkm' => 70]);

    Livewire::test(CreateRapor::class)
        ->fillForm([
            'siswa_id' => $siswa->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'semester' => Semester::Ganjil->value,
            'kelas_id' => $kelas->id,
            'nilaiRapor' => [
                ['mata_pelajaran_id' => $matematika->id, 'kkm' => 70, 'nilai_pengetahuan' => 95, 'nilai_keterampilan' => 65, 'sikap' => 'SB'],
            ],
            'nilaiEkstrakurikuler' => [],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $rapor = Rapor::query()->whereBelongsTo($siswa)->sole();

    expect($rapor->semester)->toBe(Semester::Ganjil)
        ->and($rapor->nilaiRapor->sole())
        ->predikat_pengetahuan->toBe('A')
        ->predikat_keterampilan->toBe('D')
        ->sikap->toBe('SB');
});

it('rejects a second report card for the same student, year and semester', function () {
    $rapor = Rapor::factory()->create(['semester' => Semester::Genap]);

    Livewire::test(CreateRapor::class)
        ->fillForm([
            'siswa_id' => $rapor->siswa_id,
            'tahun_ajaran_id' => $rapor->tahun_ajaran_id,
            'semester' => Semester::Genap->value,
            'kelas_id' => $rapor->kelas_id,
            'nilaiRapor' => [],
        ])
        ->call('create')
        ->assertHasFormErrors(['semester' => 'unique']);

    expect(Rapor::query()->count())->toBe(1);
});
