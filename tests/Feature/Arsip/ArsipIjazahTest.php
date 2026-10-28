<?php

use App\Filament\Resources\Ijazahs\Pages\ManageIjazahs;
use App\Models\Ijazah;
use App\Models\Siswa;
use App\Models\User;
use App\Services\Arsip\ImporArsipIjazah;
use App\Services\Arsip\SimpanBerkasArsip;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    // A fresh disk per test: reusing one races Windows' delayed directory deletion.
    $this->disk = 'arsip-uji-'.Str::lower(Str::random(10));
    config(['filesystems.arsip' => $this->disk]);
    Storage::fake($this->disk);
});

afterEach(function () {
    rescue(fn () => File::deleteDirectory(storage_path("framework/testing/disks/{$this->disk}")), report: false);
});

it('reads the NIS or NISN from a file name, ignoring the page suffix', function (string $namaFile, ?string $nomor) {
    expect(ImporArsipIjazah::nomorIdentitas($namaFile))->toBe($nomor);
})->with([
    'satu file' => ['0133438941.pdf', '0133438941'],
    'halaman bernomor' => ['0133438941-2.jpg', '0133438941'],
    'garis bawah' => ['250001_1.png', '250001'],
    'depan / belakang' => ['250001_belakang.jpg', '250001'],
    'kata halaman' => ['250001 hal 3.jpg', '250001'],
    'bukan nomor' => ['ijazah ahmad.pdf', null],
]);

it('scales a large photo down to JPEG and keeps a PDF as it is', function () {
    $penyimpan = app(SimpanBerkasArsip::class);

    $foto = $penyimpan->simpan(UploadedFile::fake()->image('ijazah.png', 4000, 3000), 'ijazah/2020');
    $pdf = $penyimpan->simpan(UploadedFile::fake()->createWithContent('ijazah.pdf', '%PDF-1.4 isi asli'), 'ijazah/2020');

    [$lebar, $tinggi] = getimagesizefromstring(Storage::disk($this->disk)->get($foto));

    expect($foto)->toStartWith('ijazah/2020/')->toEndWith('.jpg')
        ->and([$lebar, $tinggi])->toBe([2000, 1500])
        ->and($pdf)->toEndWith('.pdf')
        ->and(Storage::disk($this->disk)->get($pdf))->toBe('%PDF-1.4 isi asli');
});

it('files a graduation year under the graduates named by the files', function () {
    $siswa = Siswa::factory()->create(['nis' => '250001', 'nisn' => '0133438941', 'nama_lengkap' => 'Ahmad Fauzi']);
    $sudahDiarsip = Ijazah::factory()->create(['tahun_lulus' => 2015, 'nis' => '140007', 'berkas' => []]);

    $hasil = app(ImporArsipIjazah::class)->impor(2015, [
        UploadedFile::fake()->image('0133438941-2.jpg'),
        UploadedFile::fake()->image('0133438941-1.jpg'),
        UploadedFile::fake()->create('140007.pdf', 10, 'application/pdf'),
        UploadedFile::fake()->create('0099887766.pdf', 10, 'application/pdf'),
        UploadedFile::fake()->create('scan tanpa nomor.pdf', 10, 'application/pdf'),
    ], buatEntriBaru: true);

    $ijazahSiswa = Ijazah::query()->where('siswa_id', $siswa->id)->sole();
    $alumniLama = Ijazah::query()->where('nisn', '0099887766')->sole();

    expect($hasil)->toBe(['diperbarui' => 1, 'dibuat' => 2, 'tidakCocok' => ['scan tanpa nomor.pdf']])
        ->and($ijazahSiswa)
        ->tahun_lulus->toBe(2015)
        ->nama->toBe('Ahmad Fauzi')
        ->nis->toBe('250001')
        ->and($ijazahSiswa->berkas)->toHaveCount(2)
        ->and($sudahDiarsip->fresh()->berkas)->toHaveCount(1)
        ->and($alumniLama)->nama->toBeNull()->siswa_id->toBeNull();

    Storage::disk($this->disk)->assertExists([...$ijazahSiswa->berkas, ...$alumniLama->berkas]);
});

it('reports unknown numbers instead of creating rows when asked to', function () {
    $hasil = app(ImporArsipIjazah::class)->impor(2015, [UploadedFile::fake()->create('0099887766.pdf', 10, 'application/pdf')], buatEntriBaru: false);

    expect($hasil['tidakCocok'])->toBe(['0099887766.pdf'])
        ->and(Ijazah::query()->exists())->toBeFalse();
});

it('links a diploma typed in by hand to the student with the same NISN', function () {
    $siswa = Siswa::factory()->create(['nisn' => '0133438941', 'nama_lengkap' => 'Ahmad Fauzi']);

    $ijazah = Ijazah::query()->create(['tahun_lulus' => 2024, 'nisn' => '0133438941']);

    expect($ijazah)->siswa_id->toBe($siswa->id)->nama->toBe('Ahmad Fauzi');
});

it('removes files from storage when they are taken off a diploma or the diploma is deleted', function () {
    Storage::disk($this->disk)->put('ijazah/2020/a.jpg', 'a');
    Storage::disk($this->disk)->put('ijazah/2020/b.jpg', 'b');
    $ijazah = Ijazah::factory()->create(['berkas' => ['ijazah/2020/a.jpg', 'ijazah/2020/b.jpg']]);

    $ijazah->update(['berkas' => ['ijazah/2020/b.jpg']]);

    Storage::disk($this->disk)->assertMissing('ijazah/2020/a.jpg')->assertExists('ijazah/2020/b.jpg');

    $ijazah->delete();

    Storage::disk($this->disk)->assertMissing('ijazah/2020/b.jpg');
});

it('previews every page through a short-lived link and records who looked', function () {
    Storage::disk($this->disk)->put('ijazah/2020/a.pdf', '%PDF');
    Storage::disk($this->disk)->put('ijazah/2020/b.jpg', 'jpg');
    Storage::disk($this->disk)->buildTemporaryUrlsUsing(fn (string $path) => "https://arsip.test/{$path}?ttl=10m");
    $ijazah = Ijazah::factory()->create(['tahun_lulus' => 2020, 'nama' => 'Ahmad Fauzi', 'berkas' => ['ijazah/2020/a.pdf', 'ijazah/2020/b.jpg']]);
    $admin = userWithPermissions(['ViewAny:Ijazah', 'View:Ijazah']);

    actingAs($admin);

    Livewire::test(ManageIjazahs::class)
        ->mountAction(TestAction::make('lihat')->table($ijazah))
        ->assertMountedActionModalSeeHtml([
            '<iframe src="https://arsip.test/ijazah/2020/a.pdf?ttl=10m"',
            '<img src="https://arsip.test/ijazah/2020/b.jpg?ttl=10m"',
        ])
        ->assertMountedActionModalSee('Halaman 2 dari 2');

    expect(Activity::query()->where('event', 'ijazah_dilihat')->sole())
        ->subject_id->toBe($ijazah->id)
        ->causer_id->toBe($admin->id);
});

it('keeps the diploma archive away from users without permission', function () {
    actingAs(User::factory()->create());

    Livewire::test(ManageIjazahs::class)->assertForbidden();
});
