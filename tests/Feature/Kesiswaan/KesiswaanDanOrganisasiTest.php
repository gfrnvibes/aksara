<?php

use App\Enums\Semester;
use App\Filament\Guru\Pages\OrganisasiSaya;
use App\Filament\Guru\Pages\TugasSaya;
use App\Filament\Guru\Widgets\AksiHarian;
use App\Filament\Guru\Widgets\PeranDanAksesCepat;
use App\Filament\Guru\Widgets\TimelineTugasGuru;
use App\Filament\Resources\Ekstrakurikulers\Pages\ManageEkstrakurikulers;
use App\Filament\Resources\PelanggaranSiswas\Pages\ManagePelanggaranSiswas;
use App\Filament\Resources\PelanggaranSiswas\PelanggaranSiswaResource;
use App\Filament\Resources\PrestasiSiswas\Pages\ManagePrestasiSiswas;
use App\Filament\Resources\PrestasiSiswas\PrestasiSiswaResource;
use App\Models\AnggotaOrganisasi;
use App\Models\Ekstrakurikuler;
use App\Models\Gtk;
use App\Models\KegiatanOrganisasi;
use App\Models\Kelas;
use App\Models\NilaiEkstrakurikuler;
use App\Models\PelanggaranSiswa;
use App\Models\PrestasiSiswa;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->tahun = TahunAjaran::factory()->aktif()->create();
});

it('lets wk kesiswaan manage discipline, achievements, and extracurricular advisors', function () {
    $kesiswaan = Gtk::factory()->denganAkun()->create();
    $kesiswaan->user->assignRole(RoleSeeder::WK_KESISWAAN);
    $pembina = Gtk::factory()->create();
    $siswa = Siswa::factory()->create();

    actingAs($kesiswaan->user);

    $this->get(PelanggaranSiswaResource::getUrl())->assertOk();
    $this->get(PrestasiSiswaResource::getUrl())->assertOk();

    Livewire::test(ManagePelanggaranSiswas::class)
        ->callAction('create', [
            'tahun_ajaran_id' => $this->tahun->id,
            'siswa_id' => $siswa->id,
            'tanggal' => today()->toDateString(),
            'kategori' => 'ringan',
            'pelanggaran' => 'Terlambat upacara',
            'poin' => 5,
            'status' => 'proses',
        ])
        ->assertHasNoActionErrors();

    Livewire::test(ManagePrestasiSiswas::class)
        ->callAction('create', [
            'tahun_ajaran_id' => $this->tahun->id,
            'siswa_id' => $siswa->id,
            'tanggal' => today()->toDateString(),
            'tingkat' => 'kabupaten',
            'nama_prestasi' => 'KSM Matematika',
            'peringkat' => 'Juara 1',
        ])
        ->assertHasNoActionErrors();

    Livewire::test(ManageEkstrakurikulers::class)
        ->callAction('create', [
            'nama' => 'OSIS',
            'pembina_id' => $pembina->id,
            'is_wajib' => false,
            'is_aktif' => true,
        ])
        ->assertHasNoActionErrors();

    expect(PelanggaranSiswa::query()->where('siswa_id', $siswa->id)->count())->toBe(1)
        ->and(PrestasiSiswa::query()->where('siswa_id', $siswa->id)->count())->toBe(1)
        ->and(Ekstrakurikuler::query()->where('nama', 'OSIS')->value('pembina_id'))->toBe($pembina->id);

    // Kepala madrasah has read-only access
    $kepala = User::factory()->create()->assignRole(RoleSeeder::KEPALA_MADRASAH);
    actingAs($kepala);
    $this->get(PelanggaranSiswaResource::getUrl())->assertOk();
    $this->get(PrestasiSiswaResource::getUrl())->assertOk();
    expect($kepala->can('Create:PelanggaranSiswa'))->toBeFalse()
        ->and($kepala->can('Create:PrestasiSiswa'))->toBeFalse();
});

it('lets an assigned advisor manage members, activities, and report grades in OrganisasiSaya', function () {
    $pembina = Gtk::factory()->denganAkun()->create();
    $pembina->user->assignRole(RoleSeeder::PEMBINA_ORGANISASI);
    $guruLain = Gtk::factory()->denganAkun()->create();

    $ekskul = Ekstrakurikuler::factory()->create([
        'nama' => 'Pramuka',
        'pembina_id' => $pembina->id,
        'is_aktif' => true,
    ]);
    $siswa = Siswa::factory()->for(Kelas::factory())->create();

    Filament::setCurrentPanel('guru');

    actingAs($guruLain->user);
    $this->get(OrganisasiSaya::getUrl())->assertForbidden();

    actingAs($pembina->user);
    $this->get(OrganisasiSaya::getUrl())->assertOk();

    $component = Livewire::test(OrganisasiSaya::class)
        ->assertSuccessful()
        ->callAction('tambahAnggota', [
            'siswa_id' => $siswa->id,
            'jabatan' => 'Ketua',
            'catatan' => 'Aktif',
        ])
        ->assertHasNoActionErrors()
        ->callAction('catatKegiatan', [
            'tanggal' => today()->toDateString(),
            'materi' => 'Latihan PBB',
            'jumlah_hadir' => 1,
            'catatan' => 'Berjalan lancar',
        ])
        ->assertHasNoActionErrors();

    $anggota = AnggotaOrganisasi::query()->where('ekstrakurikuler_id', $ekskul->id)->firstOrFail();

    $component
        ->callAction(TestAction::make('nilaiRapor')->table($anggota), [
            'nilai' => 'A',
            'keterangan' => 'Sangat aktif memimpin regu',
        ])
        ->assertHasNoActionErrors();

    $semester = Semester::berdasarkanTanggal(now());
    $nilai = NilaiEkstrakurikuler::query()
        ->where('ekstrakurikuler_id', $ekskul->id)
        ->whereHas('rapor', fn ($q) => $q->where('siswa_id', $siswa->id)->where('tahun_ajaran_id', $this->tahun->id)->where('semester', $semester))
        ->first();

    expect($anggota->jabatan)->toBe('Ketua')
        ->and(KegiatanOrganisasi::query()->where('ekstrakurikuler_id', $ekskul->id)->count())->toBe(1)
        ->and($nilai?->nilai)->toBe('A')
        ->and($nilai?->keterangan)->toBe('Sangat aktif memimpin regu');
});

it('shows additional positions and quick access cards on Guru dashboard when a teacher holds extra roles', function () {
    $guruBiasa = Gtk::factory()->denganAkun()->create();
    Filament::setCurrentPanel('guru');

    actingAs($guruBiasa->user);
    expect(PeranDanAksesCepat::canView())->toBeFalse();
    Livewire::test(AksiHarian::class)->assertSee('Panel Ujian');

    $guruRangkap = Gtk::factory()->denganAkun()->create();
    $guruRangkap->user->assignRole([RoleSeeder::WK_KESISWAAN, 'bendahara']);
    Kelas::factory()->create(['nama' => 'VIII-A', 'wali_kelas_id' => $guruRangkap->id]);
    Ekstrakurikuler::factory()->create(['nama' => 'OSIS', 'pembina_id' => $guruRangkap->id, 'is_aktif' => true]);

    actingAs($guruRangkap->user);
    expect(PeranDanAksesCepat::canView())->toBeTrue();

    Livewire::test(PeranDanAksesCepat::class)
        ->assertSee('WK Kesiswaan')
        ->assertSee('Bendahara')
        ->assertSee('Wali Kelas')
        ->assertSee('VIII-A')
        ->assertSee('Pembina Ekskul')
        ->assertSee('OSIS');

    Livewire::test(TimelineTugasGuru::class)
        ->assertSee('Ringkasan Tugas Administrasi')
        ->assertSee('Kehadiran GTK Hari Ini')
        ->assertSee('Buka Timeline Tugas');

    $this->get(TugasSaya::getUrl())->assertOk()
        ->assertSee('Kehadiran GTK Hari Ini')
        ->assertSee('Jurnal Mengajar Hari Ini')
        ->assertSee('Perangkat Ajar')
        ->assertSee('Input Nilai Mata Pelajaran');

    expect(TugasSaya::getNavigationBadge())->not->toBeNull()
        ->and(TugasSaya::getNavigationBadgeColor())->toBe('danger');
});
