<?php

use App\Enums\JenisOrangTua;
use App\Enums\StatusPpdb;
use App\Filament\Resources\Ppdbs\Pages\ListPpdbs;
use App\Models\Kelas;
use App\Models\Ppdb;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->disk = 'arsip-uji-'.Str::lower(Str::random(8));
    config(['filesystems.arsip' => $this->disk]);
    Storage::fake($this->disk);

    $this->tahunAjaran = TahunAjaran::factory()->aktif()->create(['nama' => '2027/2028', 'ppdb_dibuka' => now()->subMonth()]);
    $this->kelas7A = Kelas::factory()->create(['tingkat' => 7, 'nama' => '7A']);
});

it('moves the selected registrations to a status and skips those that cannot move there', function () {
    actingAs(userWithPermissions(['ViewAny:Ppdb', 'Update:Ppdb']));
    $terverifikasi = Ppdb::factory()->for($this->tahunAjaran)->status(StatusPpdb::Terverifikasi)->create();
    $menunggu = Ppdb::factory()->for($this->tahunAjaran)->create();

    Livewire::test(ListPpdbs::class)
        ->selectTableRecords([$terverifikasi->id, $menunggu->id])
        ->callAction(TestAction::make('terimaMassal')->table()->bulk())
        ->assertNotified('1 dari 2 pendaftar diubah menjadi Diterima');

    expect($terverifikasi->fresh()->status)->toBe(StatusPpdb::Diterima)
        ->and($menunggu->fresh()->status)->toBe(StatusPpdb::Menunggu);

    Livewire::test(ListPpdbs::class)
        ->selectTableRecords([$menunggu->id])
        ->callAction(TestAction::make('tolakMassal')->table()->bulk(), ['catatan' => 'Berkas KK tidak terbaca'])
        ->assertHasNoFormErrors();

    expect($menunggu->fresh())
        ->status->toBe(StatusPpdb::Ditolak)
        ->catatan->toBe('Berkas KK tidak terbaca');
});

it('enters accepted applicants into the Buku Induk in a first-grade class', function () {
    actingAs(userWithPermissions(['ViewAny:Ppdb', 'Update:Ppdb', 'Create:Siswa']));
    Storage::disk($this->disk)->put('ppdb/foto.jpg', 'foto');
    $diterima = Ppdb::factory()->for($this->tahunAjaran)->status(StatusPpdb::Diterima)->create([
        'nama_lengkap' => 'Ahmad Fauzi',
        'nama_wali' => 'Paman Ujang',
        'berkas' => ['foto' => 'ppdb/foto.jpg'],
    ]);
    $belumDiterima = Ppdb::factory()->for($this->tahunAjaran)->status(StatusPpdb::Terverifikasi)->create();

    Livewire::test(ListPpdbs::class)
        ->selectTableRecords([$diterima->id, $belumDiterima->id])
        ->mountAction(TestAction::make('jadikanSiswa')->table()->bulk())
        ->tap(fn ($halaman) => expect(array_column($halaman->get('mountedActions.0.data.calon'), 'ppdb_id'))->toBe([$diterima->id]))
        ->setActionData(['kelas_id' => $this->kelas7A->id, 'tanggal_diterima' => '2027-07-13', 'calon' => [['ppdb_id' => $diterima->id, 'nis' => '2027001']]])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified('1 siswa ditambahkan ke kelas 7A');

    $siswa = Siswa::query()->sole();

    expect($siswa)
        ->nis->toBe('2027001')
        ->nama_lengkap->toBe('Ahmad Fauzi')
        ->kelas_id->toBe($this->kelas7A->id)
        ->diterima_di_tingkat->toBe(7)
        ->and($siswa->orangTua->pluck('nama', 'jenis.value')->all())->toBe([
            JenisOrangTua::Ayah->value => $diterima->nama_ayah_kandung,
            JenisOrangTua::Ibu->value => $diterima->nama_ibu_kandung,
            JenisOrangTua::Wali->value => 'Paman Ujang',
        ])
        ->and($siswa->riwayatKelas()->where('tahun_ajaran_id', $this->tahunAjaran->id)->value('kelas_id'))->toBe($this->kelas7A->id)
        ->and($diterima->fresh())->status->toBe(StatusPpdb::JadiSiswa)->siswa_id->toBe($siswa->id)
        ->and($belumDiterima->fresh()->status)->toBe(StatusPpdb::Terverifikasi);

    $diterima->delete();

    Storage::disk($this->disk)->assertExists($siswa->foto);
});

it('skips applicants already in the Buku Induk and years that are not active yet', function () {
    actingAs(userWithPermissions(['ViewAny:Ppdb', 'Update:Ppdb', 'Create:Siswa']));
    $sudahAda = Ppdb::factory()->for($this->tahunAjaran)->status(StatusPpdb::Diterima)->create(['nisn' => '0011223344']);
    Siswa::factory()->create(['nisn' => '0011223344']);
    $tahunDepan = Ppdb::factory()->for(TahunAjaran::factory()->create(['nama' => '2028/2029']))->status(StatusPpdb::Diterima)->create();

    Livewire::test(ListPpdbs::class)
        ->filterTable('tahun_ajaran_id', null)
        ->selectTableRecords([$sudahAda->id, $tahunDepan->id])
        ->callAction(TestAction::make('jadikanSiswa')->table()->bulk(), [
            'kelas_id' => $this->kelas7A->id,
            'tanggal_diterima' => '2027-07-13',
            'calon' => [['ppdb_id' => $sudahAda->id, 'nis' => '2027001'], ['ppdb_id' => $tahunDepan->id, 'nis' => '2027002']],
        ])
        ->assertNotified('0 siswa ditambahkan ke kelas 7A');

    expect(Siswa::query()->count())->toBe(1)
        ->and($sudahAda->fresh()->status)->toBe(StatusPpdb::Diterima)
        ->and($tahunDepan->fresh()->status)->toBe(StatusPpdb::Diterima);
});

it('keeps registrations away from users without permission', function () {
    actingAs(User::factory()->create());

    Livewire::test(ListPpdbs::class)->assertForbidden();
});
