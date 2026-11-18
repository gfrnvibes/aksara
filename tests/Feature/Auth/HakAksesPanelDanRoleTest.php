<?php

use App\Filament\Bendahara\Pages\BukuKas;
use App\Filament\Bendahara\Pages\PenggajianGuru;
use App\Filament\Bendahara\Pages\TagihanSiswa as HalamanTagihan;
use App\Filament\Guru\Pages\BukuPiket;
use App\Filament\Guru\Pages\JurnalMengajar;
use App\Filament\Guru\Pages\KehadiranGtk;
use App\Filament\Guru\Pages\LegerKelasSaya;
use App\Filament\Guru\Pages\PresensiKelas;
use App\Filament\Guru\Pages\RaporKelasSaya;
use App\Filament\Guru\Pages\RekapHarian;
use App\Filament\Pages\BackupData;
use App\Filament\Pages\LegerNilai;
use App\Filament\Resources\Ijazahs\IjazahResource;
use App\Filament\Resources\Jadwals\JadwalResource;
use App\Filament\Resources\MutasiSiswas\MutasiSiswaResource;
use App\Filament\Resources\Ppdbs\PpdbResource;
use App\Filament\Resources\Siswas\SiswaResource;
use App\Filament\Resources\SuratKeluars\SuratKeluarResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\AlurTujuanPembelajaran;
use App\Models\Gtk;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\Kepegawaian\AkunGtk;
use App\Services\Keuangan\PencatatanKeuangan;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('keeps bendahara out of admin panel and allows guru panel when also a teacher', function () {
    $gtk = Gtk::factory()->denganAkun()->create();
    $gtk->user->assignRole('bendahara');

    actingAs($gtk->user);

    expect($gtk->user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse()
        ->and($gtk->user->canAccessPanel(Filament::getPanel('bendahara')))->toBeTrue()
        ->and($gtk->user->canAccessPanel(Filament::getPanel('guru')))->toBeTrue()
        ->and($gtk->user->canAccessPanel(Filament::getPanel('ujian')))->toBeTrue();

    $this->get('/admin')->assertForbidden();
    $this->get('/bendahara')->assertOk();
    $this->get('/guru')->assertOk();
});

it('grants kepala madrasah read-only access across admin, bendahara, and ujian panels', function () {
    $kepala = User::factory()->create()->assignRole(RoleSeeder::KEPALA_MADRASAH);
    $tahun = TahunAjaran::factory()->aktif()->create();
    $siswa = Siswa::factory()->create();

    actingAs($kepala);

    expect($kepala->canAccessPanel(Filament::getPanel('admin')))->toBeTrue()
        ->and($kepala->canAccessPanel(Filament::getPanel('bendahara')))->toBeTrue()
        ->and($kepala->canAccessPanel(Filament::getPanel('ujian')))->toBeTrue()
        ->and($kepala->canAccessPanel(Filament::getPanel('guru')))->toBeFalse();

    // Admin: read-only, no write, no backup
    $this->get(SiswaResource::getUrl())->assertOk();
    $this->get(LegerNilai::getUrl())->assertOk();
    $this->get(SiswaResource::getUrl('create'))->assertForbidden();
    $this->get(BackupData::getUrl())->assertForbidden();
    expect($kepala->can('Update:Siswa'))->toBeFalse()
        ->and($kepala->can('Delete:Siswa'))->toBeFalse();

    // Bendahara: can view pages and export report, cannot mutate
    $this->get('/bendahara')->assertOk();
    $this->get('/bendahara/buku-kas')->assertOk();
    $this->get('/bendahara/tagihan-siswa')->assertOk();
    $this->get('/bendahara/penggajian-guru')->assertOk();
    $this->get(route('keuangan.laporan', ['mulai' => '2026-01-01', 'selesai' => '2026-12-31']))->assertOk();

    Filament::setCurrentPanel('bendahara');
    Livewire::test(BukuKas::class)->assertActionHidden('catat');
    Livewire::test(HalamanTagihan::class)->assertActionHidden('buatTagihan');
    Livewire::test(PenggajianGuru::class)->assertActionHidden('buatGaji');

    expect(fn () => app(PencatatanKeuangan::class)->catat([
        'tanggal' => today()->toDateString(),
        'jenis' => 'pemasukan',
        'kategori' => 'Donasi',
        'uraian' => 'Uji',
        'nominal' => 10000,
        'metode' => 'tunai',
    ]))->toThrow(HttpException::class);
});

it('scopes tata usaha and operator permissions without ambiguity', function () {
    $tu = User::factory()->create()->assignRole(RoleSeeder::TATA_USAHA);
    actingAs($tu);

    expect($tu->canAccessPanel(Filament::getPanel('admin')))->toBeTrue()
        ->and($tu->canAccessPanel(Filament::getPanel('bendahara')))->toBeFalse();

    $this->get(SuratKeluarResource::getUrl())->assertOk();
    $this->get(PpdbResource::getUrl())->assertOk();
    $this->get(MutasiSiswaResource::getUrl())->assertOk();
    $this->get(IjazahResource::getUrl())->assertOk();
    $this->get(SiswaResource::getUrl())->assertOk();

    $this->get(SiswaResource::getUrl('create'))->assertForbidden();
    $this->get(JadwalResource::getUrl())->assertForbidden();
    $this->get(UserResource::getUrl())->assertForbidden();

    // Operator also manages PPDB, Mutasi, Ijazah, and Siswa, but not Users
    $operator = User::factory()->create()->assignRole('operator');
    actingAs($operator);
    $this->get(SiswaResource::getUrl('create'))->assertOk();
    $this->get(PpdbResource::getUrl())->assertOk();
    $this->get(MutasiSiswaResource::getUrl())->assertOk();
    $this->get(IjazahResource::getUrl())->assertOk();
    $this->get(UserResource::getUrl())->assertForbidden();
});

it('allows only owner, wk_kurikulum, kepala_madrasah, or super_admin to print another teacher ATP', function () {
    $pemilik = Gtk::factory()->denganAkun()->create();
    $tahun = TahunAjaran::factory()->aktif()->create();
    $kelas = Kelas::factory()->create();
    $mapel = MataPelajaran::factory()->create();
    $atp = AlurTujuanPembelajaran::query()->create([
        'tahun_ajaran_id' => $tahun->id,
        'jenjang' => 'MTs',
        'kelas_id' => $kelas->id,
        'mata_pelajaran_id' => $mapel->id,
        'gtk_id' => $pemilik->id,
        'fase' => 'D',
    ]);

    $bendahara = User::factory()->create()->assignRole('bendahara');
    $tu = User::factory()->create()->assignRole(RoleSeeder::TATA_USAHA);
    $kurikulum = User::factory()->create()->assignRole(RoleSeeder::WK_KURIKULUM);
    $kepala = User::factory()->create()->assignRole(RoleSeeder::KEPALA_MADRASAH);

    actingAs($bendahara)->get(route('atp.cetak', $atp))->assertForbidden();
    actingAs($tu)->get(route('atp.cetak', $atp))->assertForbidden();
    actingAs($kurikulum)->get(route('atp.cetak', $atp))->assertOk();
    actingAs($kepala)->get(route('atp.cetak', $atp))->assertOk();
    actingAs($pemilik->user)->get(route('atp.cetak', $atp))->assertOk();
});

it('centralizes daily input pages in Guru panel and keeps RekapHarian in Admin panel', function () {
    $adminPages = Filament::getPanel('admin')->getPages();

    expect($adminPages)->toContain(RekapHarian::class)
        ->not->toContain(PresensiKelas::class)
        ->not->toContain(JurnalMengajar::class)
        ->not->toContain(KehadiranGtk::class)
        ->not->toContain(BukuPiket::class);
});

it('grants wali kelas role access to guru panel without opening admin or blocking operator password reset', function () {
    $gtk = Gtk::factory()->denganAkun()->create();
    $gtk->user->assignRole(RoleSeeder::WALI_KELAS);
    Kelas::factory()->create(['wali_kelas_id' => $gtk->id]);

    $operator = User::factory()->create()->assignRole('operator');

    actingAs($gtk->user);
    Filament::setCurrentPanel('guru');

    expect($gtk->user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse()
        ->and($gtk->user->canAccessPanel(Filament::getPanel('bendahara')))->toBeFalse()
        ->and($gtk->user->canAccessPanel(Filament::getPanel('guru')))->toBeTrue()
        ->and($gtk->user->canAccessPanel(Filament::getPanel('ujian')))->toBeTrue()
        ->and(RaporKelasSaya::canAccess())->toBeTrue()
        ->and(LegerKelasSaya::canAccess())->toBeTrue()
        ->and(AkunGtk::bolehDireset($gtk, $operator))->toBeTrue();
});

it('restricts LegerKelasSaya to homeroom classes only', function () {
    $tahun = TahunAjaran::factory()->aktif()->create();
    $wali = Gtk::factory()->denganAkun()->create();
    $guruBiasa = Gtk::factory()->denganAkun()->create();
    $kelasWali = Kelas::factory()->create(['wali_kelas_id' => $wali->id]);
    $kelasLain = Kelas::factory()->create();

    actingAs($guruBiasa->user);
    Filament::setCurrentPanel('guru');
    $this->get('/guru/leger-kelas-saya')->assertForbidden();

    actingAs($wali->user);
    $this->get('/guru/leger-kelas-saya')->assertOk();

    $component = Livewire::test(LegerKelasSaya::class)->assertSuccessful();
    expect($component->instance()->leger()?->kelas->id)->toBe($kelasWali->id);

    $component->set('data.kelas_id', $kelasLain->id);
    expect($component->instance()->leger())->toBeNull();
});
