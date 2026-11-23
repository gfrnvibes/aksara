<?php

use App\Filament\Resources\BukuPerpustakaans\Pages\ManageBukuPerpustakaans;
use App\Models\AkreditasiBukti;
use App\Models\BukuPerpustakaan;
use App\Models\MataPelajaran;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\Akreditasi\PetaBanPdm;
use App\Services\Perpustakaan\ImporBukuPerpustakaan;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

it('renders public library page, streams PDF, syncs with akreditasi, and serves mobile API', function (): void {
    config(['filesystems.perpustakaan' => 'local']);
    Storage::fake('local');
    Storage::fake('public');

    $tahun = TahunAjaran::factory()->create(['is_aktif' => true]);
    $mapel = MataPelajaran::factory()->create(['nama' => 'Fiqih']);

    $pdfPath = 'perpustakaan/pdf/fiqih-7.pdf';
    Storage::disk('local')->put($pdfPath, '%PDF-1.4 test content');

    $buku = BukuPerpustakaan::query()->create([
        'judul' => 'Fiqih Kelas VII MTs',
        'penulis' => 'Kemenag RI',
        'penerbit' => 'Direktorat KSKK Madrasah',
        'tahun_terbit' => 2024,
        'isbn' => '9786022446125',
        'kategori' => 'pai',
        'tingkat' => 7,
        'mata_pelajaran_id' => $mapel->id,
        'deskripsi' => 'Buku paket Fiqih resmi untuk kelas VII MTs.',
        'file_pdf' => $pdfPath,
        'is_aktif' => true,
    ]);

    // 1. Landing page & navbar
    get(route('home'))->assertOk()->assertSee(route('perpustakaan.index'));
    get(route('perpustakaan.index'))->assertOk()->assertSee('Fiqih Kelas VII MTs');

    // 2. Public PDF reader increments count
    get(route('perpustakaan.baca', $buku))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    expect($buku->fresh()->jumlah_dibaca)->toBe(1);

    // 3. Admin Filament resource
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate(config('filament-shield.super_admin.name'), 'web'));
    Filament::setCurrentPanel('admin');
    actingAs($admin);
    Livewire::test(ManageBukuPerpustakaans::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$buku]);

    // 4. Akreditasi sync
    $peta = app(PetaBanPdm::class);
    $ringkasan = $peta->ringkasanStatistik($tahun);
    expect($ringkasan['jumlah_koleksi_perpustakaan_digital'])->toBe(1)
        ->and($ringkasan['total_pembaca_perpustakaan'])->toBe(1);

    $tagged = $peta->autoTagBukti($tahun, $admin->id);
    expect($tagged)->toBeGreaterThanOrEqual(1)
        ->and(AkreditasiBukti::query()->where('kode_butir', 'butir_08')->where('sumber_tipe', 'perpustakaan')->where('sumber_id', $buku->id)->exists())->toBeTrue();

    // 5. Mobile API for Siswa / Orang Tua
    $siswa = Siswa::factory()->create();
    $siswaUser = User::factory()->create(['siswa_id' => $siswa->id]);
    Sanctum::actingAs($siswaUser);

    $res = getJson('/api/v1/perpustakaan?kategori=pai&tingkat=7')
        ->assertOk()
        ->assertJsonFragment(['judul' => 'Fiqih Kelas VII MTs', 'mapel' => 'Fiqih']);

    $bacaUrl = $res->json('buku.0.baca_url');
    expect($bacaUrl)->toBeString()->toContain('signature=');

    get(route('perpustakaan.viewer', $buku))->assertOk()->assertSee('pdf.min.js');
    get($bacaUrl)->assertOk()->assertSee('pdf.min.js');

    // 6. Batch import from SIBI/Kemenag URL filters only MTs grades (7-9) and stores PDF locally
    Http::fake([
        'https://buku.kemdikbud.go.id/*' => Http::response('%PDF-1.4 sibi book', 200, ['Content-Type' => 'application/pdf']),
    ]);

    $hasil = app(ImporBukuPerpustakaan::class)->imporDariBaris([
        [
            'judul' => 'IPA Kelas VIII SMP/MTs',
            'penulis' => 'Kemdikbud',
            'kategori' => 'paket',
            'sumber' => 'sibi',
            'tingkat' => 8,
            'pdf_url' => 'https://buku.kemdikbud.go.id/ipa-8.pdf',
        ],
        [
            'judul' => 'Matematika Kelas IV SD',
            'kategori' => 'paket',
            'sumber' => 'sibi',
            'tingkat' => 4,
            'pdf_url' => 'https://buku.kemdikbud.go.id/mtk-4.pdf',
        ],
    ], $admin->id);

    expect($hasil['berhasil'])->toBe(1)
        ->and($hasil['dilewati'])->toBe(1)
        ->and($hasil['gagal'])->toBe([])
        ->and(BukuPerpustakaan::query()->where('judul', 'IPA Kelas VIII SMP/MTs')->exists())->toBeTrue()
        ->and(BukuPerpustakaan::query()->where('judul', 'Matematika Kelas IV SD')->exists())->toBeFalse();
});

it('sorts buku paket and smp/mts books first on public library and mobile api', function (): void {
    $umum = BukuPerpustakaan::query()->create([
        'judul' => 'Ensiklopedia Umum Terbaru',
        'kategori' => 'ensiklopedia',
        'sumber' => 'elipski',
        'tingkat' => null,
        'file_pdf' => 'perpustakaan/pdf/ensiklopedia.pdf',
        'is_aktif' => true,
    ]);

    $paiSmp = BukuPerpustakaan::query()->create([
        'judul' => 'Akidah Akhlak Kelas VIII MTs',
        'kategori' => 'pai',
        'sumber' => 'kemenag',
        'tingkat' => 8,
        'file_pdf' => 'perpustakaan/pdf/akidah-8.pdf',
        'is_aktif' => true,
    ]);

    $paket9 = BukuPerpustakaan::query()->create([
        'judul' => 'Bahasa Indonesia Kelas IX',
        'kategori' => 'paket',
        'sumber' => 'sibi',
        'tingkat' => 9,
        'file_pdf' => 'perpustakaan/pdf/bindo-9.pdf',
        'is_aktif' => true,
    ]);

    $paket7 = BukuPerpustakaan::query()->create([
        'judul' => 'Matematika Kelas VII',
        'kategori' => 'paket',
        'sumber' => 'sibi',
        'tingkat' => 7,
        'file_pdf' => 'perpustakaan/pdf/mtk-7.pdf',
        'is_aktif' => true,
    ]);

    // Tambah buku umum dengan ID paling besar agar memastikan tidak mengalahkan buku paket/SMP
    $umumTerbaru = BukuPerpustakaan::query()->create([
        'judul' => 'Dakwah Populer Terbaru',
        'kategori' => 'dakwah',
        'sumber' => 'elipski',
        'tingkat' => null,
        'file_pdf' => 'perpustakaan/pdf/dakwah.pdf',
        'is_aktif' => true,
    ]);

    $response = get(route('perpustakaan.index'))->assertOk();
    $paginator = $response->viewData('paginator');
    $urutanPublik = $paginator->getCollection()->pluck('id')->all();

    expect($urutanPublik)->toBe([
        $paket7->id,
        $paket9->id,
        $paiSmp->id,
        $umumTerbaru->id,
        $umum->id,
    ]);

    $siswa = Siswa::factory()->create();
    $siswaUser = User::factory()->create(['siswa_id' => $siswa->id]);
    Sanctum::actingAs($siswaUser);

    $apiRes = getJson('/api/v1/perpustakaan')->assertOk();
    $urutanApi = collect($apiRes->json('buku'))->pluck('id')->all();

    expect($urutanApi)->toBe($urutanPublik);
});

it('displays shortened author (dkk), publisher, and source metadata on ebook cards', function (): void {
    $bukuMulti = BukuPerpustakaan::query()->create([
        'judul' => 'Ilmu Pengetahuan Alam Kelas VII',
        'penulis' => 'Victoriani Inabuy, Cece Sutia, Okky Fajar Tri Maryana',
        'penerbit' => 'Pusat Kurikulum dan Perbukuan',
        'sumber' => 'sibi',
        'kategori' => 'paket',
        'tingkat' => 7,
        'file_pdf' => 'perpustakaan/pdf/ipa-7.pdf',
        'is_aktif' => true,
    ]);

    expect($bukuMulti->penulisRingkas())->toBe('Victoriani Inabuy, dkk.')
        ->and($bukuMulti->sumberLabel())->toBe('SIBI Kemendikbud');

    get(route('perpustakaan.index'))
        ->assertOk()
        ->assertSee('Victoriani Inabuy, dkk.')
        ->assertSee('Pusat Kurikulum dan Perbukuan · SIBI Kemendikbud')
        ->assertDontSee('Victoriani Inabuy, Cece Sutia, Okky Fajar Tri Maryana');
});
