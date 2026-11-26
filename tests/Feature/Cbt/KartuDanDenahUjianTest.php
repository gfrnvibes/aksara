<?php

use App\Enums\Semester;
use App\Filament\Ujian\Resources\KegiatanUjians\KegiatanUjianResource;
use App\Filament\Ujian\Resources\KegiatanUjians\Pages\KelolaRuangDanDenah;
use App\Models\Gtk;
use App\Models\KegiatanUjian;
use App\Models\Kelas;
use App\Models\PenempatanUjian;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\Cbt\PemetaanDenahUjian;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->tahun = TahunAjaran::factory()->aktif()->create(['nama' => '2026/2027']);
});

it('grants access to super admin, operator, tata usaha, and read-only kepala madrasah, while blocking guru and wk kurikulum', function () {
    $kegiatan = KegiatanUjian::query()->create([
        'nama' => 'PAS Ganjil 2026/2027',
        'tahun_ajaran_id' => $this->tahun->id,
        'semester' => Semester::Ganjil,
    ]);

    foreach ([config('filament-shield.super_admin.name'), 'operator', RoleSeeder::TATA_USAHA] as $role) {
        $user = User::factory()->create()->assignRole($role);
        actingAs($user);
        expect($user->canAccessPanel(Filament::getPanel('ujian')))->toBeTrue();
        $this->get(KegiatanUjianResource::getUrl(panel: 'ujian'))->assertOk();
        $this->get(KegiatanUjianResource::getUrl('create', panel: 'ujian'))->assertOk();
    }

    $kepala = User::factory()->create()->assignRole(RoleSeeder::KEPALA_MADRASAH);
    actingAs($kepala);
    $this->get(KegiatanUjianResource::getUrl(panel: 'ujian'))->assertOk();
    $this->get(KegiatanUjianResource::getUrl('create', panel: 'ujian'))->assertForbidden();

    Filament::setCurrentPanel('ujian');
    Livewire::test(KelolaRuangDanDenah::class, ['record' => $kegiatan->id])
        ->assertActionHidden('tambahRuang')
        ->assertActionHidden('petakanPeserta');

    $kurikulum = User::factory()->create()->assignRole(RoleSeeder::WK_KURIKULUM);
    actingAs($kurikulum)->get(KegiatanUjianResource::getUrl(panel: 'ujian'))->assertForbidden();

    $guru = Gtk::factory()->denganAkun()->create();
    actingAs($guru->user)->get(KegiatanUjianResource::getUrl(panel: 'ujian'))->assertForbidden();
});

it('maps two-seat desks alternating across grade levels and swaps seats cleanly', function () {
    $kelas7 = Kelas::factory()->create(['tingkat' => 7, 'nama' => 'VII-A']);
    $kelas8 = Kelas::factory()->create(['tingkat' => 8, 'nama' => 'VIII-A']);

    Siswa::factory()->for($kelas7)->count(3)->create();
    Siswa::factory()->for($kelas8)->count(3)->create();

    $kegiatan = KegiatanUjian::query()->create([
        'nama' => 'PAS Ganjil',
        'tahun_ajaran_id' => $this->tahun->id,
        'semester' => Semester::Ganjil,
        'prefix_nomor_peserta' => '26-',
    ]);

    $ruang = $kegiatan->ruang()->create([
        'kode' => 'R-01',
        'nama' => 'Ruang 01',
        'urutan' => 1,
        'jumlah_kolom' => 4,
        'kursi_per_meja' => 2,
        'kapasitas' => 8,
    ]);

    $layanan = app(PemetaanDenahUjian::class);
    $jumlah = $layanan->petakan($kegiatan, [], 'selang_seling');

    expect($jumlah)->toBe(6);

    $penempatan = $kegiatan->penempatan()->with('kelas')->orderBy('nomor_kursi')->get()->keyBy('nomor_kursi');

    expect($penempatan->pluck('nomor_peserta')->unique()->count())->toBe(6);

    // Every 2-seat desk (1&2, 3&4, 5&6) must pair two different grade levels.
    foreach ([[1, 2], [3, 4], [5, 6]] as [$kiri, $kanan]) {
        expect($penempatan[$kiri]->kelas->tingkat)->not->toBe($penempatan[$kanan]->kelas->tingkat);
    }

    // Swap seats 1 and 2
    $awal1 = $penempatan[1];
    $awal2 = $penempatan[2];
    $layanan->tukarKursi($awal1, $awal2);

    expect($awal1->fresh()->nomor_kursi)->toBe(2)
        ->and($awal2->fresh()->nomor_kursi)->toBe(1)
        ->and($awal1->fresh()->ruang_ujian_id)->toBe($ruang->id);
});

it('renders bulk A4 exam cards with cut guides and room seating plan for authorized users', function () {
    $tu = User::factory()->create()->assignRole(RoleSeeder::TATA_USAHA);
    actingAs($tu);

    $kelas = Kelas::factory()->create(['tingkat' => 7, 'nama' => 'VII-A']);
    $siswa = Siswa::factory()->for($kelas)->create(['nama_lengkap' => 'Budi Santoso']);

    $ketua = Gtk::factory()->create(['nama' => 'H. Ketua Panitia, S.Pd', 'nip' => '198001012005011001']);

    $kegiatan = KegiatanUjian::query()->create([
        'nama' => 'PTS Genap',
        'tahun_ajaran_id' => $this->tahun->id,
        'semester' => Semester::Genap,
        'tanggal_mulai' => '2026-06-05', // Jumat
        'tanggal_selesai' => '2026-06-08', // Senin (Minggu 07/06/2026 harus dilewati)
        'ketua_panitia_id' => $ketua->id,
    ]);

    $ruang = $kegiatan->ruang()->create([
        'kode' => 'R-01',
        'nama' => 'Ruang 01',
        'urutan' => 1,
        'jumlah_kolom' => 4,
        'kursi_per_meja' => 2,
        'kapasitas' => 16,
    ]);

    PenempatanUjian::query()->create([
        'kegiatan_ujian_id' => $kegiatan->id,
        'ruang_ujian_id' => $ruang->id,
        'siswa_id' => $siswa->id,
        'kelas_id' => $kelas->id,
        'nomor_peserta' => '26-07-001',
        'nomor_kursi' => 1,
        'urutan_posisi' => 1,
    ]);

    $this->tahun->update([
        'kepala_madrasah' => 'Drs. H. Ahmad Kepala',
        'nip_kepala_madrasah' => '197001012000011001',
    ]);

    $this->get(route('ujian.kartu', $kegiatan))
        ->assertOk()
        ->assertSee('Budi Santoso')
        ->assertSee('26-07-001')
        ->assertSee('R-01')
        ->assertSee('Drs. H. Ahmad Kepala')
        ->assertSee('H. Ketua Panitia, S.Pd')
        ->assertSee('Ketua Panitia Ujian,')
        ->assertSee('NISN')
        ->assertDontSee('NIS / NISN')
        ->assertDontSee('Harap membawa kartu ini selama ujian berlangsung.')
        ->assertSee('border: 1px dashed');

    $this->get(route('ujian.denah', $kegiatan))
        ->assertOk()
        ->assertSee('MEJA PENGAWAS')
        ->assertSee('Ruang 01')
        ->assertSee('Drs. H. Ahmad Kepala')
        ->assertSee('Ketua Panitia Ujian,')
        ->assertSee('Budi Santoso');

    $this->get(route('ujian.daftar-hadir', $kegiatan))
        ->assertOk()
        ->assertSee('DAFTAR HADIR PESERTA')
        ->assertSee('RUANG 01')
        ->assertSee('05/06/2026')
        ->assertSee('06/06/2026')
        ->assertDontSee('07/06/2026')
        ->assertSee('08/06/2026')
        ->assertSee('Budi Santoso');

    $this->get(route('ujian.semuanya', $kegiatan))
        ->assertOk()
        ->assertSee('Paket Cetak Lengkap');
});

it('excludes transferred-out, graduated, and expelled students from exam seating and active student scopes', function () {
    $kelas = Kelas::factory()->create(['tingkat' => 7, 'nama' => 'VII-A']);
    $aktif = Siswa::factory()->for($kelas)->create(['nama_lengkap' => 'Siswa Aktif']);
    $pindah = Siswa::factory()->for($kelas)->create(['nama_lengkap' => 'Siswa Pindah', 'status' => \App\Enums\StatusSiswa::Pindah]);
    $keluar = Siswa::factory()->for($kelas)->create(['nama_lengkap' => 'Siswa Keluar', 'status' => \App\Enums\StatusSiswa::Keluar]);
    $lulus = Siswa::factory()->for($kelas)->create(['nama_lengkap' => 'Siswa Lulus', 'status' => \App\Enums\StatusSiswa::Lulus]);

    $kegiatan = KegiatanUjian::query()->create([
        'nama' => 'PAS Ganjil',
        'tahun_ajaran_id' => $this->tahun->id,
        'semester' => Semester::Ganjil,
    ]);

    $kegiatan->ruang()->create([
        'kode' => 'R-01',
        'nama' => 'Ruang 01',
        'urutan' => 1,
        'jumlah_kolom' => 4,
        'kursi_per_meja' => 2,
        'kapasitas' => 10,
    ]);

    $jumlah = app(PemetaanDenahUjian::class)->petakan($kegiatan);

    expect($jumlah)->toBe(1)
        ->and($kegiatan->penempatan()->pluck('siswa_id')->all())->toBe([$aktif->id])
        ->and(Siswa::query()->aktif()->pluck('id')->all())->toBe([$aktif->id]);
});

it('edits and deletes an exam room from the room and seating page for super admin and operator', function (string $role) {
    $user = User::factory()->create()->assignRole($role);
    actingAs($user);
    Filament::setCurrentPanel('ujian');

    $kegiatan = KegiatanUjian::query()->create([
        'nama' => 'PAS Ganjil',
        'tahun_ajaran_id' => $this->tahun->id,
        'semester' => Semester::Ganjil,
    ]);

    $ruang = $kegiatan->ruang()->create([
        'kode' => 'R-01',
        'nama' => 'Ruang 01',
        'urutan' => 1,
        'jumlah_kolom' => 4,
        'kursi_per_meja' => 2,
        'kapasitas' => 32,
    ]);

    Livewire::test(KelolaRuangDanDenah::class, ['record' => $kegiatan->id])
        ->assertSee('Cetak Ruang Ini')
        ->assertSee('Edit Ruang')
        ->assertSee('Hapus Ruang')
        ->callAction('editRuang', [
            'kode' => 'R-01A',
            'nama' => 'Ruang Lab IPA',
            'urutan' => 2,
            'jumlah_kolom' => 5,
            'kursi_per_meja' => 2,
            'kapasitas' => 40,
        ], ['ruang' => $ruang->id])
        ->assertHasNoActionErrors();

    expect($ruang->fresh())
        ->kode->toBe('R-01A')
        ->nama->toBe('Ruang Lab IPA')
        ->urutan->toBe(2)
        ->jumlah_kolom->toBe(5)
        ->kapasitas->toBe(40);

    Livewire::test(KelolaRuangDanDenah::class, ['record' => $kegiatan->id])
        ->callAction('hapusRuang', arguments: ['ruang' => $ruang->id])
        ->assertHasNoActionErrors();

    expect($kegiatan->ruang()->count())->toBe(0);
})->with([
    'super_admin' => ['super_admin'],
    'operator' => ['operator'],
]);
