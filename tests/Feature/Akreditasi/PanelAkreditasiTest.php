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
