<?php

use App\Filament\Akreditasi\Pages\DasborAkreditasi;
use App\Filament\Akreditasi\Resources\AkreditasiDokumens\AkreditasiDokumenResource;
use App\Models\AkreditasiDokumen;
use App\Models\GaleriKegiatan;
use App\Models\Gtk;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\Akreditasi\PembuatBundelAkreditasi;
use App\Services\Akreditasi\PenyusunDokumenAkreditasiAi;
use App\Services\Akreditasi\PetaBanPdm;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->tahun = TahunAjaran::factory()->aktif()->create(['nama' => '2026/2027']);
});

it('grants tim_akreditasi access to akreditasi panel without opening admin panel, and blocks regular guru', function () {
    $gtkTim = Gtk::factory()->denganAkun()->create();
    $gtkTim->user->assignRole(RoleSeeder::TIM_AKREDITASI);

    actingAs($gtkTim->user);

    expect($gtkTim->user->canAccessPanel(Filament::getPanel('akreditasi')))->toBeTrue()
        ->and($gtkTim->user->canAccessPanel(Filament::getPanel('guru')))->toBeTrue()
        ->and($gtkTim->user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();

    $this->get('/akreditasi')->assertOk();
    $this->get(AkreditasiDokumenResource::getUrl(panel: 'akreditasi'))->assertOk();
    $this->get('/admin')->assertForbidden();

    $guruBiasa = Gtk::factory()->denganAkun()->create();
    actingAs($guruBiasa->user);
    expect($guruBiasa->user->canAccessPanel(Filament::getPanel('akreditasi')))->toBeFalse();
    $this->get('/akreditasi')->assertForbidden();

    $kepala = User::factory()->create()->assignRole(RoleSeeder::KEPALA_MADRASAH);
    actingAs($kepala)->get('/akreditasi')->assertOk();
    Filament::setCurrentPanel('akreditasi');
    Livewire::test(DasborAkreditasi::class)
        ->assertActionHidden('inisialisasi')
        ->assertActionHidden('generateSemuaAi');
});

it('initializes 20 standard documents, auto-tags evidence, and generates grounded drafts', function () {
    $kelas = Kelas::factory()->create();
    Siswa::factory()->for($kelas)->count(3)->create();
    GaleriKegiatan::query()->create(['judul' => 'Pawai Moderasi Beragama', 'berkas' => []]);

    $peta = app(PetaBanPdm::class);
    expect($peta->inisialisasiDokumen($this->tahun))->toBe(20)
        ->and($peta->inisialisasiDokumen($this->tahun))->toBe(0);

    expect($peta->autoTagBukti($this->tahun))->toBeGreaterThanOrEqual(1);

    $ksp = AkreditasiDokumen::query()->where('tahun_ajaran_id', $this->tahun->id)->where('kode', 'dw_1')->firstOrFail();
    $html = app(PenyusunDokumenAkreditasiAi::class)->susun($ksp);

    expect($html)->toContain('3')
        ->and($ksp->fresh()->ringkasan_data['jumlah_siswa_aktif'])->toBe(3)
        ->and($ksp->fresh()->status)->toBe('draf');
});

it('builds the complete visitasi ZIP bundle with 6 PDFs, DOCX DKA, XLSX mapping, evidence folders, deck, and checklist', function () {
    $tim = User::factory()->create()->assignRole(RoleSeeder::TIM_AKREDITASI);
    actingAs($tim);

    $peta = app(PetaBanPdm::class);
    $peta->inisialisasiDokumen($this->tahun, $tim->id);

    $ksp = AkreditasiDokumen::query()->where('tahun_ajaran_id', $this->tahun->id)->where('kode', 'dw_1')->firstOrFail();
    app(PenyusunDokumenAkreditasiAi::class)->susun($ksp);

    $this->get(route('akreditasi.dokumen.cetak', $ksp))->assertOk();

    $zipPath = app(PembuatBundelAkreditasi::class)->buatZip($this->tahun);
    $zip = new ZipArchive;
    expect($zip->open($zipPath))->toBeTrue();

    $entries = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entries[] = (string) $zip->getNameIndex($i);
    }
    $zip->close();
    @unlink($zipPath);

    $daftar = implode("\n", $entries);
    expect($daftar)->toContain('01_Dokumen_Wajib_Sispena/01_KSP_MTs.pdf')
        ->toContain('01_Dokumen_Wajib_Sispena/06_Dokumentasi_Lingkungan_Belajar.pdf')
        ->toContain('02_Draft_Isian_DKA_14_Butir.docx')
        ->toContain('02_Mapping_Bukti_14_Butir.xlsx')
        ->toContain('03_Folder_Bukti_Per_Butir/Butir_01/00_Daftar_Bukti.txt')
        ->toContain('03_Folder_Bukti_Per_Butir/Butir_14/00_Daftar_Bukti.txt')
        ->toContain('04_Deck_Presentasi_Kepala_Madrasah.pdf')
        ->toContain('05_Lembar_Ceklis_Kesiapan_Lapangan.pdf');
});

it('shows AI loading and progress bar on accreditation dashboard and document pages', function () {
    $tim = User::factory()->create()->assignRole(RoleSeeder::TIM_AKREDITASI);
    actingAs($tim);

    app(PetaBanPdm::class)->inisialisasiDokumen($this->tahun, $tim->id);
    $dok = AkreditasiDokumen::query()->where('tahun_ajaran_id', $this->tahun->id)->firstOrFail();

    \Illuminate\Support\Facades\Bus::fake();
    $batch = \Illuminate\Support\Facades\Bus::batch([
        new \App\Jobs\SusunDokumenAkreditasiAi($dok->id),
    ])->name('akreditasi-ai-'.$this->tahun->id)->dispatch();

    \Illuminate\Support\Facades\Cache::put(DasborAkreditasi::kunciBatchAktif($this->tahun->id), [
        'batch_id' => $batch->id,
        'dibuat_pada' => now()->timestamp,
    ], now()->addHours(2));

    Filament::setCurrentPanel('akreditasi');
    Livewire::test(DasborAkreditasi::class)
        ->assertSee('Menyusun Draf Dokumen Akreditasi AI')
        ->assertSee('wire:poll.2s="cekProgresAi"', false);

    $this->get(AkreditasiDokumenResource::getUrl(panel: 'akreditasi'))
        ->assertOk()
        ->assertSee('akreditasi-ai-mulai')
        ->assertSee('Menyusun draf AI:');

    $this->get(AkreditasiDokumenResource::getUrl('edit', ['record' => $dok], panel: 'akreditasi'))
        ->assertOk()
        ->assertSee('akreditasi-ai-mulai')
        ->assertSee('Menyusun draf AI:');
});

it('calculates weighted accreditation readiness instead of reporting 100 percent for drafts alone', function () {
    $peta = app(PetaBanPdm::class);
    $peta->inisialisasiDokumen($this->tahun);

    AkreditasiDokumen::query()
        ->where('tahun_ajaran_id', $this->tahun->id)
        ->update(['status' => 'draf']);

    $kesiapanDraf = $peta->analisisKesiapan($this->tahun);
    expect($kesiapanDraf['persen_keseluruhan'])->toBe(20)
        ->and($kesiapanDraf['dw_final'])->toBe(0)
        ->and($kesiapanDraf['dka_final'])->toBe(0)
        ->and($kesiapanDraf['butir_terpenuhi'])->toBe(0)
        ->and($kesiapanDraf['persen_admin_guru'])->toBe(0);

    AkreditasiDokumen::query()
        ->where('tahun_ajaran_id', $this->tahun->id)
        ->update(['status' => 'final']);

    \App\Models\AkreditasiBukti::query()->create([
        'tahun_ajaran_id' => $this->tahun->id,
        'kode_butir' => 'butir_01',
        'judul' => 'Catatan Wali Kelas',
        'sumber_tipe' => 'manual',
        'berkas_path' => 'bukti/butir-01.pdf',
    ]);

    $kesiapanFinal = $peta->analisisKesiapan($this->tahun);
    expect($kesiapanFinal['dw_final'])->toBe(6)
        ->and($kesiapanFinal['dka_final'])->toBe(14)
        ->and($kesiapanFinal['butir_terpenuhi'])->toBe(1)
        ->and($kesiapanFinal['persen_keseluruhan'])->toBe(43);
});
