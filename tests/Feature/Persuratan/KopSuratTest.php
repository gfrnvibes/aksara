<?php

use App\Filament\Resources\KopSurats\KopSuratResource;
use App\Filament\Resources\KopSurats\Pages\CreateKopSurat;
use App\Filament\Resources\KopSurats\Pages\EditKopSurat;
use App\Filament\Resources\KopSurats\Pages\ListKopSurats;
use App\Models\KopSurat;
use App\Models\SuratKeluar;
use App\Services\Surat\PengaturanKop;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Storage::fake('public');
    Filament::setCurrentPanel('admin');
    actingAs(userWithPermissions(['ViewAny:KopSurat', 'View:KopSurat', 'Create:KopSurat', 'Update:KopSurat', 'View:SuratKeluar']));
});

function kopUji(string $nama = 'Kop Resmi'): KopSurat
{
    return KopSurat::create(['nama' => $nama, 'konfigurasi' => [...PengaturanKop::bawaan(), 'nama' => $nama]]);
}

function gambarKopUji(string $path = 'kop-surat/logo.png'): string
{
    Storage::disk('public')->put($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII='));

    return $path;
}

it('keeps drafts separate from the defaults and applies selected document categories independently', function () {
    $kop = kopUji('Madrasah Pertama');
    expect(PengaturanKop::untuk('surat_keluar')['legacy'])->toBeTrue();
    PengaturanKop::terapkan($kop, ['surat_keluar', 'rapor']);
    $versi = PengaturanKop::untuk('surat_keluar')['versi'];
    $kop->update(['konfigurasi' => [...$kop->konfigurasi, 'nama' => 'Madrasah Baru']]);
    expect(PengaturanKop::untuk('surat_keluar')['nama'])->toBe('Madrasah Pertama');
    PengaturanKop::terapkan($kop, ['surat_keluar']);
    expect(PengaturanKop::untuk('surat_keluar')['nama'])->toBe('Madrasah Baru')
        ->and(PengaturanKop::untuk('surat_keluar')['versi'])->not->toBe($versi)
        ->and(PengaturanKop::untuk('rapor')['nama'])->toBe('Madrasah Pertama')
        ->and(PengaturanKop::untuk('surat_keterangan')['legacy'])->toBeTrue();
});

it('preserves the letterhead of saved letters after publishing another default and changing school identity', function () {
    $kop = kopUji('Kop Arsip Lama');
    PengaturanKop::terapkan($kop, ['surat_keluar']);
    $lama = SuratKeluar::factory()->create();
    $baru = kopUji('Kop Baru');
    PengaturanKop::terapkan($baru, ['surat_keluar']);
    config(['sekolah.nama' => 'Nama global berubah']);
    $lama->update(['perihal' => 'Surat diperbarui']);
    expect($lama->fresh()->kop_surat_snapshot['nama'])->toBe('Kop Arsip Lama');
    expect(SuratKeluar::factory()->create()->kop_surat_snapshot['nama'])->toBe('Kop Baru');
    $this->get(route('surat.keluar', $lama))->assertOk()->assertSee('Kop Arsip Lama')->assertDontSee('Kop Baru');
    expect(fn () => $lama->forceFill(['kop_surat_snapshot' => PengaturanKop::untuk('surat_keluar')])->save())->toThrow(ValidationException::class);
});

it('freezes the legacy letterhead of letters created before using custom templates', function () {
    config(['sekolah.nama' => 'Madrasah Sebelum Kustomisasi']);
    $surat = SuratKeluar::factory()->create();
    PengaturanKop::terapkan(kopUji('Kop Sesudah Kustomisasi'), ['surat_keluar']);
    config(['sekolah.nama' => 'Nama berubah lagi']);
    $this->get(route('surat.keluar', $surat))->assertOk()->assertSee('MADRASAH SEBELUM KUSTOMISASI')->assertDontSee('Kop Sesudah Kustomisasi');
});

it('renders safe local images in browser and PDF without remote image dependencies', function () {
    $path = gambarKopUji();
    $kop = KopSurat::create(['nama' => 'Gambar', 'konfigurasi' => [...PengaturanKop::bawaan(), 'mode' => 'gambar', 'gambar' => $path]]);
    $html = view('cetak.pratinjau-kop', ['kop' => $kop->konfigurasi])->render();
    expect($html)->toContain('data:image/png;base64,')->not->toContain('Gambar kop belum tersedia.');
    $bytes = Pdf::loadView('cetak.pratinjau-kop', ['kop' => $kop->konfigurasi])->setPaper('a4')->output();
    expect($bytes)->toStartWith('%PDF');
    $kop->update(['konfigurasi' => [...$kop->konfigurasi, 'mode' => 'terstruktur', 'logo_kiri' => $path, 'logo_kanan' => $path]]);
    expect(view('cetak.pratinjau-kop', ['kop' => $kop->konfigurasi])->render())->toContain('Logo kiri', 'Logo kanan');
});

it('rejects missing images, external URLs, unsafe image types and invalid layout values', function () {
    expect(fn () => KopSurat::create(['nama' => 'Tidak lengkap', 'konfigurasi' => [...PengaturanKop::bawaan(), 'mode' => 'gambar']]))->toThrow(ValidationException::class);
    foreach (['https://example.com/logo.png', 'kop-surat/../secret.png', 'kop-surat/hilang.png'] as $path) {
        expect(fn () => PengaturanKop::validasi([...PengaturanKop::bawaan(), 'logo_kiri' => $path]))->toThrow(ValidationException::class);
    }
    Storage::disk('public')->put('kop-surat/berbahaya.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    expect(fn () => PengaturanKop::validasi([...PengaturanKop::bawaan(), 'gambar' => 'kop-surat/berbahaya.svg']))->toThrow(ValidationException::class);
    expect(fn () => PengaturanKop::validasi([...PengaturanKop::bawaan(), 'ukuran_huruf' => 999]))->toThrow(ValidationException::class);
});

it('creates and edits drafts on full pages, publishes defaults and downloads a preview PDF', function () {
    Livewire::test(CreateKopSurat::class)->assertSuccessful()->fillForm(['nama' => 'Kop melalui form', 'konfigurasi.nama' => 'Nama Melalui Form'])->call('create')->assertHasNoFormErrors();
    $kop = KopSurat::query()->sole();
    $page = Livewire::test(EditKopSurat::class, ['record' => $kop->getRouteKey()])->assertSuccessful()
        ->fillForm(['konfigurasi.alamat' => 'Alamat Baru'])->call('save')->assertHasNoFormErrors()
        ->callAction('jadikanDefault', ['jenis' => ['surat_keterangan']])->assertHasNoActionErrors();
    expect(PengaturanKop::untuk('surat_keterangan')['alamat'])->toBe('Alamat Baru');
    $page->callAction('pdf')->assertFileDownloaded('pratinjau-kop.pdf');
    Livewire::test(ListKopSurats::class)->assertSuccessful()->assertSee('Surat keterangan, pindah &amp; transkrip', false);
});

it('edits a draft with previously uploaded images without deleting the files used by archives', function () {
    $path = gambarKopUji();
    $kop = KopSurat::create(['nama' => 'Kop Logo', 'konfigurasi' => [...PengaturanKop::bawaan(), 'logo_kiri' => $path]]);
    PengaturanKop::terapkan($kop, ['surat_keluar']);
    $surat = SuratKeluar::factory()->create();
    Livewire::test(EditKopSurat::class, ['record' => $kop->getRouteKey()])->fillForm(['nama' => 'Nama Draf Baru', 'konfigurasi.logo_kiri' => null])->call('save')->assertHasNoFormErrors();
    Storage::disk('public')->assertExists($path);
    expect($surat->fresh()->kop_surat_snapshot['logo_kiri'])->toBe($path);
});

it('requires explicit letterhead permissions and never permits deleting a template', function () {
    $kop = kopUji();
    actingAs(userWithPermissions([]));
    Livewire::test(ListKopSurats::class)->assertForbidden();
    Livewire::test(CreateKopSurat::class)->assertForbidden();
    Livewire::test(EditKopSurat::class, ['record' => $kop->getRouteKey()])->assertForbidden();
    actingAs(userWithPermissions(['ViewAny:KopSurat', 'Update:KopSurat']));
    Livewire::test(EditKopSurat::class, ['record' => $kop->getRouteKey()])->assertSuccessful();
    expect(KopSuratResource::canDelete($kop))->toBeFalse();
});

it('previews an unsaved draft PDF without creating a template or changing defaults', function () {
    Livewire::test(CreateKopSurat::class)->fillForm(['nama' => 'Belum disimpan', 'konfigurasi.nama' => 'Pratinjau saja'])
        ->callAction('pdf')->assertFileDownloaded('pratinjau-kop.pdf');
    expect(KopSurat::count())->toBe(0)->and(DB::table('default_kop_surats')->count())->toBe(0);
});

it('stores new uploaded images, keeps old assets and escapes custom letterhead text', function () {
    $image = UploadedFile::fake()->image('logo.png', 300, 300);
    Livewire::test(CreateKopSurat::class)->fillForm([
        'nama' => 'Unggah Logo', 'konfigurasi.nama' => '<script>alert(1)</script>', 'konfigurasi.logo_kiri' => $image,
    ])->call('create')->assertHasNoFormErrors();
    $kop = KopSurat::query()->sole();
    Storage::disk('public')->assertExists($kop->konfigurasi['logo_kiri']);
    $html = view('cetak.pratinjau-kop', ['kop' => $kop->konfigurasi])->render();
    expect($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')->not->toContain('<script>');
});

it('accepts wide and tall logos beyond the former pixel limits and renders them in PDF', function (int $width, int $height) {
    $image = UploadedFile::fake()->image('logo.png', $width, $height);
    Livewire::test(CreateKopSurat::class)->fillForm([
        'nama' => 'Logo Bebas Rasio', 'konfigurasi.logo_kiri' => $image,
    ])->call('create')->assertHasNoFormErrors();
    $kop = KopSurat::query()->sole();
    $bytes = Storage::disk('public')->get($kop->konfigurasi['logo_kiri']);
    $size = getimagesizefromstring($bytes);
    expect($size[0])->toBe($width)->and($size[1])->toBe($height);
    expect(Pdf::loadView('cetak.pratinjau-kop', ['kop' => $kop->konfigurasi])->setPaper('a4')->output())->toStartWith('%PDF');
})->with(['landscape' => [8200, 120], 'portrait' => [120, 4500]]);
