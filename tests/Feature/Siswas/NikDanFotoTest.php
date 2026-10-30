<?php

use App\Enums\IsiDokumen;
use App\Exports\BukuIndukExport;
use App\Models\MataPelajaran;
use App\Models\Siswa;
use App\Services\Arsip\SimpanBerkasArsip;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    // A fresh disk per test: reusing one races Windows' delayed directory deletion.
    $this->disk = 'arsip-uji-'.Str::lower(Str::random(10));
    config(['filesystems.arsip' => $this->disk]);
    Storage::fake($this->disk);
});

afterEach(function () {
    rescue(fn () => File::deleteDirectory(storage_path("framework/testing/disks/{$this->disk}")), report: false);
});

it('exports the NIK as text so its sixteen digits survive Excel', function () {
    $siswa = Siswa::factory()->create(['nik' => '0205010101100001']);

    $reader = new Reader;
    $reader->open(BukuIndukExport::excelSiswa(BukuIndukExport::muatSiswa(Siswa::query()->whereKey($siswa->id)), IsiDokumen::Siswa));
    $baris = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $baris[] = $row->toArray();
        }
    }

    $reader->close();

    expect(array_combine($baris[0], $baris[1])['NIK'])->toBe('0205010101100001');
});

it('prints the NIK on the Buku Induk sheet and the student list', function () {
    actingAs(userWithPermissions(['View:Siswa']));
    $siswa = Siswa::factory()->create(['nik' => '3205010101100001']);

    get(route('siswa.cetak', ['siswa' => $siswa, 'isi' => 'siswa']))
        ->assertOk()
        ->assertSeeText(['Nomor Induk Kependudukan', '3205010101100001']);

    expect(BukuIndukExport::pdfRekap(BukuIndukExport::muatSiswa(Siswa::query()->whereKey($siswa->id)), IsiDokumen::Siswa))
        ->toStartWith('%PDF');
});

it('stores a scaled-down pas foto on the archive disk and removes the replaced one', function () {
    $lama = app(SimpanBerkasArsip::class)->simpan(UploadedFile::fake()->image('lama.jpg', 300, 400), 'foto-siswa', Siswa::SISI_FOTO);
    $baru = app(SimpanBerkasArsip::class)->simpan(UploadedFile::fake()->image('baru.jpg', 1500, 2000), 'foto-siswa', Siswa::SISI_FOTO);
    $siswa = Siswa::factory()->create(['foto' => $lama]);

    $siswa->update(['foto' => $baru]);

    [$lebar, $tinggi] = getimagesizefromstring(Storage::disk($this->disk)->get($baru));

    expect([$lebar, $tinggi])->toBe([600, 800]);
    Storage::disk($this->disk)->assertMissing($lama)->assertExists($baru);

    $siswa->forceDelete();

    Storage::disk($this->disk)->assertMissing($baru);
});

it('embeds the pas foto in a PDF and links it for the browser', function () {
    $path = app(SimpanBerkasArsip::class)->simpan(UploadedFile::fake()->image('foto.jpg', 300, 400), 'foto-siswa', Siswa::SISI_FOTO);
    Storage::disk($this->disk)->buildTemporaryUrlsUsing(fn (string $lokasi) => "https://arsip.test/{$lokasi}?ttl=10m");
    $siswa = Siswa::factory()->create(['foto' => $path]);

    expect($siswa->sumberFoto(untukPdf: true))->toStartWith('data:image/jpeg;base64,')
        ->and($siswa->sumberFoto(untukPdf: false))->toBe("https://arsip.test/{$path}?ttl=10m")
        ->and(Siswa::factory()->create(['foto' => null])->sumberFoto(untukPdf: true))->toBeNull();
});

it('seeds the subjects with their abbreviations without overwriting edited ones', function () {
    MataPelajaran::factory()->create(['nama' => 'Matematika', 'singkatan' => 'MTK']);
    MataPelajaran::factory()->create(['nama' => 'Fiqih', 'singkatan' => null]);

    $this->seed(MasterDataSeeder::class);

    expect(MataPelajaran::query()->pluck('singkatan', 'nama')->all())->toMatchArray([
        'Matematika' => 'MTK',
        'Fiqih' => 'FQH',
        'Al-Qur\'an Hadist' => 'QURDIS',
        'Pendidikan Jasmani, Olahraga dan Kesehatan' => 'PJOK',
    ]);
});
