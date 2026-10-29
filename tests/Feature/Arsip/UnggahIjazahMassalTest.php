<?php

use App\Enums\StatusSiswa;
use App\Filament\Resources\Ijazahs\Pages\UnggahIjazahMassal;
use App\Models\Ijazah;
use App\Models\Kelas;
use App\Models\RiwayatKelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Services\Arsip\ImporArsipIjazah;
use App\Services\Arsip\PencocokLulusan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    // A fresh disk per test: reusing one races Windows' delayed directory deletion.
    $this->disk = 'arsip-uji-'.Str::lower(Str::random(10));
    config(['filesystems.arsip' => $this->disk]);
    Storage::fake($this->disk);

    $this->ahmad = Siswa::factory()->create(['nis' => '250001', 'nama_lengkap' => 'Ahmad Fauzi', 'status' => StatusSiswa::Lulus, 'tanggal_tamat' => '2026-06-20']);
    $this->siti = Siswa::factory()->create(['nis' => '250002', 'nama_lengkap' => 'Siti Nurhaliza', 'status' => StatusSiswa::Lulus, 'tanggal_tamat' => '2026-06-20']);
});

afterEach(function () {
    rescue(fn () => File::deleteDirectory(storage_path("framework/testing/disks/{$this->disk}")), report: false);
});

it('guesses the graduate from the number or the full name in a file name', function (string $namaFile, ?string $harapan) {
    $alumniLama = Ijazah::factory()->create(['tahun_lulus' => 2026, 'nama' => 'Dewi Lestari', 'nis' => '9001']);
    Siswa::factory()->create(['nama_lengkap' => 'Ahmad Fauzi', 'status' => StatusSiswa::Lulus, 'tanggal_tamat' => '2019-06-20']);

    $target = [
        'ahmad' => "siswa:{$this->ahmad->id}",
        'dewi' => "ijazah:{$alumniLama->id}",
    ][$harapan] ?? null;

    expect(app(PencocokLulusan::class)->tebak($namaFile, 2026))->toBe($target);
})->with([
    'NIS' => ['250001-2.jpg', 'ahmad'],
    'nama lengkap' => ['Ijazah AHMAD FAUZI.pdf', 'ahmad'],
    'nama dengan pemisah' => ['scan_ahmad-fauzi_depan.jpg', 'ahmad'],
    'arsip alumni lama' => ['dewi lestari 2026.jpg', 'dewi'],
    'nama sebagian' => ['ahmad.jpg', null],
    'nama kamera' => ['IMG_20261020_134053[1].jpg', null],
]);

it('finds the final-grade students of the academic year ending that year', function () {
    $kelasSembilan = Kelas::factory()->create(['tingkat' => 9]);
    $budi = Siswa::factory()->create(['nama_lengkap' => 'Budi Santoso']);
    RiwayatKelas::factory()->for($budi)->for($kelasSembilan)->for(TahunAjaran::factory()->create(['nama' => '2025/2026']))->create();

    expect(app(PencocokLulusan::class)->tebak('budi santoso.jpg', 2026))->toBe("siswa:{$budi->id}");
});

it('lists the year\'s graduates first and every other student after them', function () {
    $alumniLama = Ijazah::factory()->create(['tahun_lulus' => 2026, 'nama' => 'Dewi Lestari', 'nis' => '9001']);
    $adikKelas = Siswa::factory()->create(['nis' => '260010', 'nama_lengkap' => 'Zaki Adik Kelas']);

    $pilihan = app(PencocokLulusan::class)->pilihan(2026);

    expect(array_keys($pilihan))->toBe(['Lulusan 2026', 'Siswa lainnya'])
        ->and(array_keys($pilihan['Lulusan 2026']))->toBe(["ijazah:{$alumniLama->id}", "siswa:{$this->ahmad->id}", "siswa:{$this->siti->id}"])
        ->and($pilihan['Siswa lainnya'])->toBe(["siswa:{$adikKelas->id}" => '260010 - Zaki Adik Kelas (Aktif)']);
});

it('files several pages under one graduate in the order given', function () {
    $hasil = app(ImporArsipIjazah::class)->simpanPasangan(2026, [
        ['target' => "siswa:{$this->ahmad->id}", 'file' => UploadedFile::fake()->createWithContent('b.pdf', 'halaman-1')],
        ['target' => 'baru:Dewi Lestari', 'file' => UploadedFile::fake()->createWithContent('c.pdf', 'dewi-1')],
        ['target' => "siswa:{$this->ahmad->id}", 'file' => UploadedFile::fake()->createWithContent('a.pdf', 'halaman-2')],
        ['target' => 'baru:dewi lestari ', 'file' => UploadedFile::fake()->createWithContent('d.pdf', 'dewi-2')],
    ]);

    $ijazahAhmad = Ijazah::query()->where('siswa_id', $this->ahmad->id)->sole();
    $ijazahDewi = Ijazah::query()->where('nama', 'Dewi Lestari')->sole();

    expect($hasil)->toBe(['diperbarui' => 0, 'dibuat' => 2])
        ->and(array_map(fn (string $path): string => Storage::disk($this->disk)->get($path), $ijazahAhmad->berkas))->toBe(['halaman-1', 'halaman-2'])
        ->and($ijazahDewi->berkas)->toHaveCount(2)
        ->and($ijazahDewi->siswa_id)->toBeNull();
});

it('adds pages to the graduate archived earlier instead of making a second diploma', function () {
    $ijazah = Ijazah::query()->create(['tahun_lulus' => 2026, 'siswa_id' => $this->ahmad->id]);

    $hasil = app(ImporArsipIjazah::class)->simpanPasangan(2026, [
        ['target' => "siswa:{$this->ahmad->id}", 'file' => UploadedFile::fake()->createWithContent('a.pdf', 'isi')],
    ]);

    expect($hasil)->toBe(['diperbarui' => 1, 'dibuat' => 0])
        ->and($ijazah->fresh()->berkas)->toHaveCount(1)
        ->and(Ijazah::query()->count())->toBe(1);
});

it('lists every uploaded file with its guessed graduate and saves the checked pairs', function () {
    config(['livewire.temporary_file_upload.directory' => 'livewire-tmp-'.Str::lower(Str::random(8))]);
    actingAs(userWithPermissions(['ViewAny:Ijazah', 'Create:Ijazah']));

    $halaman = Livewire::test(UnggahIjazahMassal::class)
        ->fillForm([
            'tahun_lulus' => 2026,
            'berkas' => [
                UploadedFile::fake()->image('ahmad fauzi.jpg'),
                UploadedFile::fake()->image('IMG_20261020_134053.jpg'),
            ],
        ]);

    $baris = collect($halaman->get('data.pasangan'));
    $tebakan = $baris->pluck('lulusan', 'nama_file')->all();

    expect($tebakan)->toBe(['ahmad fauzi.jpg' => "siswa:{$this->ahmad->id}", 'IMG_20261020_134053.jpg' => null]);

    $kunciKamera = $baris->search(fn (array $isi): bool => $isi['nama_file'] === 'IMG_20261020_134053.jpg');

    $halaman
        ->set("data.pasangan.{$kunciKamera}.lulusan", "siswa:{$this->siti->id}")
        ->callAction('simpan')
        ->assertNotified('Ijazah 2026 tersimpan');

    expect(Ijazah::query()->where('siswa_id', $this->ahmad->id)->sole()->berkas)->toHaveCount(1)
        ->and(Ijazah::query()->where('siswa_id', $this->siti->id)->sole()->berkas)->toHaveCount(1);
});

it('requires a graduate for every file before saving', function () {
    config(['livewire.temporary_file_upload.directory' => 'livewire-tmp-'.Str::lower(Str::random(8))]);
    actingAs(userWithPermissions(['ViewAny:Ijazah', 'Create:Ijazah']));

    $halaman = Livewire::test(UnggahIjazahMassal::class)
        ->fillForm(['tahun_lulus' => 2026, 'berkas' => [UploadedFile::fake()->image('IMG_0001.jpg')]]);
    $kunci = array_key_first($halaman->get('data.pasangan'));

    $halaman
        ->callAction('simpan')
        ->assertHasErrors(["data.pasangan.{$kunci}.lulusan" => 'required']);

    expect(Ijazah::query()->exists())->toBeFalse();
});

it('keeps the upload page away from users who cannot add diplomas', function () {
    actingAs(userWithPermissions(['ViewAny:Ijazah']));

    Livewire::test(UnggahIjazahMassal::class)->assertForbidden();
});
