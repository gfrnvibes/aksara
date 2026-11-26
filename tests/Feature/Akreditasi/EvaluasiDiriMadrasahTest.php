<?php

use App\Filament\Akreditasi\Pages\EvaluasiDiriMadrasah;
use App\Filament\Akreditasi\Resources\InventarisMedias\Pages\ManageInventarisMedias;
use App\Filament\Akreditasi\Resources\PelatihanGtks\Pages\ManagePelatihanGtks;
use App\Filament\Akreditasi\Resources\ProgramRkams\Pages\ManageProgramRkams;
use App\Filament\Akreditasi\Resources\RapatMadrasahs\Pages\ManageRapatMadrasahs;
use App\Filament\Akreditasi\Resources\SupervisiPembelajarans\Pages\ManageSupervisiPembelajarans;
use App\Filament\Guru\Resources\KegiatanIbadahSiswas\Pages\ManageKegiatanIbadahSiswas;
use App\Filament\Guru\Resources\KegiatanRemedials\Pages\ManageKegiatanRemedials;
use App\Models\AiApiKey;
use App\Models\BukuPerpustakaan;
use App\Models\EdmEvaluasi;
use App\Models\EdmIndikator;
use App\Models\GaleriKegiatan;
use App\Models\Gtk;
use App\Models\InventarisMedia;
use App\Models\KegiatanIbadahSiswa;
use App\Models\KegiatanRemedial;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\PelatihanGtk;
use App\Models\PerangkatAjar;
use App\Models\PresensiGtk;
use App\Models\ProgramRkam;
use App\Models\RapatMadrasah;
use App\Models\SupervisiPembelajaran;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\Akreditasi\SinkronisasiEdmService;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('initializes 26 EDM 2026 indicators, syncs metrics from existing resources, and links gallery images', function (): void {
    Storage::fake('local');

    $tahun = TahunAjaran::factory()->aktif()->create();
    $guru = Gtk::factory()->create(['is_aktif' => true, 'jenis_gtk' => 'guru']);

    PresensiGtk::query()->create([
        'gtk_id' => $guru->id,
        'tanggal' => today(),
        'status' => 'hadir',
        'dicatat_pada' => now(),
        'pencatat_id' => User::factory()->create()->id,
    ]);

    $kelas = Kelas::factory()->create();
    $mapel = MataPelajaran::factory()->create();

    PerangkatAjar::query()->create([
        'tahun_ajaran_id' => $tahun->id,
        'kelas_id' => $kelas->id,
        'mata_pelajaran_id' => $mapel->id,
        'gtk_id' => $guru->id,
    ]);

    BukuPerpustakaan::query()->create([
        'judul' => 'Matematika Kelas VII',
        'kategori' => 'paket',
        'sumber' => 'sibi',
        'tingkat' => 7,
        'file_pdf' => 'perpustakaan/pdf/mtk-7.pdf',
        'jumlah_dibaca' => 15,
        'is_aktif' => true,
    ]);

    Storage::disk('local')->put('galeri/sholat.jpg', 'fake-image-bytes');
    GaleriKegiatan::query()->create([
        'judul' => 'Sholat Dzuhur Berjamaah',
        'kategori' => 'Keagamaan',
        'kode_butir_edm' => ['A1'],
        'tanggal_kegiatan' => today(),
        'berkas' => ['galeri/sholat.jpg'],
        'is_publik' => true,
    ]);

    $evaluasi = app(SinkronisasiEdmService::class)->siapkanEvaluasi($tahun);

    expect($evaluasi->indikators)->toHaveCount(26);

    $a1 = $evaluasi->indikators->firstWhere('kode', 'A1');
    $a2 = $evaluasi->indikators->firstWhere('kode', 'A2');
    $c1 = $evaluasi->indikators->firstWhere('kode', 'C1');

    expect($a1->metrik_sistem['jumlah_galeri_tertaut'])->toBe(1)
        ->and($a1->buktiGabungan())->toHaveCount(1)
        ->and($a2->skor_saran)->toBe(4)
        ->and($c1->skor_saran)->toBe(4)
        ->and($c1->rekap_tabel['baris'])->not->toBeEmpty();
});

it('renders EDM page in Akreditasi panel, allows scoring, upload, AI Gemini assist, print, and zip download', function (): void {
    config(['filesystems.arsip' => 'local']);
    Storage::fake('local');

    $tahun = TahunAjaran::factory()->aktif()->create();

    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate(config('filament-shield.super_admin.name'), 'web'));

    Filament::setCurrentPanel('akreditasi');
    actingAs($admin);

    $component = Livewire::test(EvaluasiDiriMadrasah::class)
        ->assertSuccessful()
        ->assertSee('A1')
        ->assertSee('Segarkan Data Sistem');

    $evaluasi = EdmEvaluasi::query()->where('tahun_ajaran_id', $tahun->id)->firstOrFail();
    $a1 = EdmIndikator::query()->where('edm_evaluasi_id', $evaluasi->id)->where('kode', 'A1')->firstOrFail();

    // Pilih skor
    $component->call('pilihSkor', $a1->id, 4);
    expect($a1->fresh()->skor_final)->toBe(4);

    // Bantu AI Gemini
    AiApiKey::query()->create([
        'nama' => 'Kunci Test',
        'api_key' => 'test-key-123',
        'daftar_model' => 'gemini-2.5-flash',
        'is_aktif' => true,
    ]);
    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => [
                    'parts' => [[
                        'text' => json_encode([
                            'skor' => 4,
                            'kondisi_nyata' => 'Seluruh siswa mengikuti sholat berjamaah dan tadarus rutin.',
                            'tindak_lanjut' => 'Pertahankan pembiasaan ibadah harian beserta catatan absensi.',
                        ]),
                    ]],
                ],
            ]],
        ], 200),
    ]);

    $component->set("catatanAiInput.{$a1->id}", 'Sholat dzuhur rutin tiap hari')
        ->call('bantuAi', $a1->id);

    expect($a1->fresh())
        ->kondisi_nyata->toContain('sholat berjamaah')
        ->dibantu_ai->toBeTrue();

    // Unggah bukti gambar
    $file = UploadedFile::fake()->image('bukti-sholat.jpg');
    $component->set("judulUpload.{$a1->id}", 'Foto Sholat Berjamaah')
        ->set("berkasUpload.{$a1->id}", $file)
        ->call('unggahBukti', $a1->id);

    expect($a1->fresh()->buktis)->toHaveCount(1);

    // Cetak bukti otentik per butir
    get(route('akreditasi.edm.cetak', $a1))
        ->assertOk()
        ->assertSee('LAPORAN BUKTI OTENTIK EVALUASI DIRI MADRASAH')
        ->assertSee('Foto Sholat Berjamaah');

    // Unduh ZIP bukti per butir
    $zipRes = get(route('akreditasi.edm.unduh-zip', $a1))
        ->assertOk();
    expect($zipRes->headers->get('content-disposition'))->toContain('EDM-A1-');
});

it('grants akreditasi panel access to users with ViewAny:EdmEvaluasi permission', function (): void {
    $perm = Permission::findOrCreate('ViewAny:EdmEvaluasi', 'web');
    $user = User::factory()->create();
    $user->givePermissionTo($perm);

    expect($user->canAccessPanel(Filament::getPanel('akreditasi')))->toBeTrue();
});

it('syncs all Phase 2 guru and akreditasi supporting modules into EDM 2026 indicators', function (): void {
    $tahun = TahunAjaran::factory()->aktif()->create();
    $kelas = Kelas::factory()->create();
    $mapel = MataPelajaran::factory()->create();
    $guru = Gtk::factory()->denganAkun()->create(['is_aktif' => true, 'jenis_gtk' => 'guru']);

    // Panel Guru modules
    Filament::setCurrentPanel('guru');
    actingAs($guru->user);
    Livewire::test(ManageKegiatanIbadahSiswas::class)->assertSuccessful();
    Livewire::test(ManageKegiatanRemedials::class)->assertSuccessful();

    KegiatanIbadahSiswa::query()->create([
        'tahun_ajaran_id' => $tahun->id,
        'kelas_id' => $kelas->id,
        'tanggal' => today(),
        'jenis' => 'sholat_berjamaah',
        'jumlah_siswa' => 30,
        'jumlah_ikut' => 29,
        'alasan_ketidakikutsertaan' => '1 siswa sakit',
    ]);

    KegiatanRemedial::query()->create([
        'tahun_ajaran_id' => $tahun->id,
        'gtk_id' => $guru->id,
        'kelas_id' => $kelas->id,
        'mata_pelajaran_id' => $mapel->id,
        'tanggal' => today(),
        'jenis' => 'remedial',
        'jumlah_sasaran' => 10,
        'jumlah_hadir' => 10,
    ]);

    // Panel Akreditasi modules
    $admin = User::factory()->create()->assignRole(Role::findOrCreate(config('filament-shield.super_admin.name'), 'web'));
    Filament::setCurrentPanel('akreditasi');
    actingAs($admin);

    Livewire::test(ManageSupervisiPembelajarans::class)->assertSuccessful();
    Livewire::test(ManageRapatMadrasahs::class)->assertSuccessful();
    Livewire::test(ManagePelatihanGtks::class)->assertSuccessful();
    Livewire::test(ManageProgramRkams::class)->assertSuccessful();
    Livewire::test(ManageInventarisMedias::class)->assertSuccessful();

    SupervisiPembelajaran::query()->create([
        'tahun_ajaran_id' => $tahun->id,
        'gtk_id' => $guru->id,
        'tanggal' => today(),
        'nomor_sk' => 'SK-01/2026',
        'penilaian_otentik' => true,
        'berkas_path' => 'edm/supervisi/sk.pdf',
    ]);

    RapatMadrasah::query()->create([
        'tahun_ajaran_id' => $tahun->id,
        'tanggal' => today(),
        'judul' => 'Rapat Penyusunan EDM & e-RKAM',
        'jenis' => 'edm_rkam',
        'melibatkan_guru' => true,
        'melibatkan_tendik' => true,
        'melibatkan_komite' => true,
    ]);

    PelatihanGtk::query()->create([
        'tahun_ajaran_id' => $tahun->id,
        'gtk_id' => $guru->id,
        'tanggal' => today(),
        'nama_kegiatan' => 'MGMP Matematika',
        'jenis' => 'kkg_mgmp',
        'dapat_bantuan_biaya' => true,
    ]);

    ProgramRkam::query()->create([
        'tahun_ajaran_id' => $tahun->id,
        'nama_program' => 'Pelatihan Kurikulum Merdeka',
        'jumlah_guru_sasaran' => 1,
        'pakai_edm_rkam' => true,
        'disahkan_kepala' => true,
    ]);

    InventarisMedia::query()->create([
        'nama' => 'Kit IPA Terpadu',
        'kategori' => 'non_digital',
        'mata_pelajaran_id' => $mapel->id,
        'jumlah' => 2,
        'kondisi_baik' => true,
        'digunakan_siswa' => true,
    ]);

    $evaluasi = app(SinkronisasiEdmService::class)->siapkanEvaluasi($tahun);
    $byKode = $evaluasi->indikators->keyBy('kode');

    expect($byKode['A1']->skor_saran)->toBe(4)
        ->and($byKode['A3']->skor_saran)->toBe(4)
        ->and($byKode['A3']->buktiGabungan())->toHaveCount(1)
        ->and($byKode['B2']->skor_saran)->toBe(4)
        ->and($byKode['B3']->skor_saran)->toBe(4)
        ->and($byKode['C4']->skor_saran)->toBe(4)
        ->and($byKode['C6']->skor_saran)->toBe(4)
        ->and($byKode['C7']->skor_saran)->toBe(4)
        ->and($byKode['D2']->skor_saran)->toBe(4)
        ->and($byKode['E1']->skor_saran)->toBe(4)
        ->and($byKode['E2']->skor_saran)->toBe(4);
});
