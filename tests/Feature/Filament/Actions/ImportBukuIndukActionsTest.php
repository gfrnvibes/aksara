<?php

use App\Enums\JenisKelamin;
use App\Enums\JenisOrangTua;
use App\Enums\Semester;
use App\Exports\BukuIndukExport;
use App\Filament\Imports\NilaiImporter;
use App\Filament\Resources\Rapors\Pages\ListRapors;
use App\Filament\Resources\Siswas\Pages\ListSiswas;
use App\Models\Ekstrakurikuler;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\NilaiRapor;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    actingAs(userWithPermissions([
        'ViewAny:Siswa', 'View:Siswa', 'Create:Siswa', 'Update:Siswa',
        'ViewAny:Rapor', 'View:Rapor', 'Create:Rapor', 'Update:Rapor',
    ]));
});

/**
 * @param  array<int, array<int, string>>  $baris
 */
function fileCsv(array $baris): UploadedFile
{
    $isi = collect($baris)
        ->map(fn (array $kolom): string => collect($kolom)->map(fn (string $nilai): string => '"'.str_replace('"', '""', $nilai).'"')->implode(','))
        ->implode("\n");

    return UploadedFile::fake()->createWithContent('import.csv', $isi."\n");
}

function jalankanImport(string $halaman, string $aksi, UploadedFile $file): Testable
{
    return Livewire::test($halaman)
        ->mountAction($aksi)
        ->fillForm(['file' => $file])
        ->callMountedAction()
        ->assertHasNoFormErrors();
}

it('creates students with their class and parents from the student template', function () {
    $kelas = Kelas::factory()->create(['nama' => 'VII-A']);

    jalankanImport(ListSiswas::class, 'importSiswa', fileCsv([
        ['NIS', 'NISN', 'Nama Lengkap', 'Jenis Kelamin (L/P)', 'Kelas', 'Tanggal Lahir', 'Nama Ayah', 'Pekerjaan Ayah', 'Nama Ibu'],
        ['250001', '0012345678', 'Ahmad Fauzi', 'Laki-laki', 'VII-A', '17/05/2012', 'Budi Santoso', 'Petani', 'Siti Aminah'],
    ]));

    $siswa = Siswa::query()->where('nis', '250001')->sole();

    expect($siswa)
        ->nisn->toBe('0012345678')
        ->jenis_kelamin->toBe(JenisKelamin::LakiLaki)
        ->kelas_id->toBe($kelas->id)
        ->tanggal_lahir->toDateString()->toBe('2012-05-17')
        ->and($siswa->ayah)->nama->toBe('Budi Santoso')->pekerjaan->toBe('Petani')
        ->and($siswa->ibu->nama)->toBe('Siti Aminah')
        ->and($siswa->wali)->toBeNull();
});

it('updates an existing student matched by NIS without clearing columns missing from the file', function () {
    $siswa = Siswa::factory()->create(['nis' => '250001', 'nama_lengkap' => 'Nama Lama', 'tempat_lahir' => 'Bandung']);

    jalankanImport(ListSiswas::class, 'importSiswa', fileCsv([
        ['NIS', 'Nama Lengkap'],
        ['250001', 'Nama Baru'],
    ]));

    expect(Siswa::query()->count())->toBe(1)
        ->and($siswa->fresh())
        ->nama_lengkap->toBe('Nama Baru')
        ->tempat_lahir->toBe('Bandung');
});

it('skips invalid student rows and keeps the valid ones', function () {
    jalankanImport(ListSiswas::class, 'importSiswa', fileCsv([
        ['NIS', 'Nama Lengkap', 'Jenis Kelamin (L/P)', 'Kelas'],
        ['250001', 'Ahmad Fauzi', 'L', ''],
        ['250002', 'Kelas Tidak Ada', 'P', 'X-Z'],
    ]))->assertNotified();

    expect(Siswa::query()->pluck('nis')->all())->toBe(['250001']);
});

it('stores semester grades with letter grades from the grade template', function () {
    $siswa = Siswa::factory()->create(['nis' => '250001']);
    $kelas = Kelas::factory()->create(['nama' => 'VII-A']);
    $matematika = MataPelajaran::factory()->create(['nama' => 'Matematika', 'kkm' => 70]);
    $pramuka = Ekstrakurikuler::factory()->create(['nama' => 'Pramuka']);

    jalankanImport(ListRapors::class, 'importNilai', fileCsv([
        ['NIS', 'Tahun Ajaran', 'Semester (1/2)', 'Kelas pada Semester', 'Sakit (hari)', 'Matematika - Pengetahuan', 'Matematika - Keterampilan', 'Ekskul Pramuka', 'Keputusan (naik/tinggal/lulus)'],
        ['250001', '2025/2026', 'Genap', 'VII-A', '2', '95', '65', 'a', 'Naik Kelas'],
    ]));

    $rapor = Rapor::query()->whereBelongsTo($siswa)->sole();

    expect($rapor)
        ->semester->toBe(Semester::Genap)
        ->kelas_id->toBe($kelas->id)
        ->sakit->toBe(2)
        ->and($rapor->tahunAjaran->nama)->toBe('2025/2026')
        ->and($rapor->nilaiRapor->sole())
        ->mata_pelajaran_id->toBe($matematika->id)
        ->kkm->toBe(70)
        ->predikat_pengetahuan->toBe('A')
        ->predikat_keterampilan->toBe('D')
        ->and($rapor->nilaiEkstrakurikuler->sole())
        ->ekstrakurikuler_id->toBe($pramuka->id)
        ->nilai->toBe('A');
});

it('updates the existing report card instead of creating a second one for the same semester', function () {
    $rapor = Rapor::factory()->create(['semester' => Semester::Ganjil]);
    $matematika = MataPelajaran::factory()->create(['nama' => 'Matematika']);

    jalankanImport(ListRapors::class, 'importNilai', fileCsv([
        ['NIS', 'Tahun Ajaran', 'Semester (1/2)', 'Matematika - Pengetahuan'],
        [$rapor->siswa->nis, $rapor->tahunAjaran->nama, '1', '88'],
    ]));

    expect(Rapor::query()->count())->toBe(1)
        ->and($rapor->nilaiRapor()->sole())
        ->mata_pelajaran_id->toBe($matematika->id)
        ->nilai_pengetahuan->toBe(88);
});

it('refuses to recreate a student whose NIS is in the trash', function () {
    Siswa::factory()->create(['nis' => '250001', 'nama_lengkap' => 'Nama Lama'])->delete();

    jalankanImport(ListSiswas::class, 'importSiswa', fileCsv([
        ['NIS', 'Nama Lengkap', 'Jenis Kelamin (L/P)'],
        ['250001', 'Nama Baru', 'L'],
    ]));

    expect(Siswa::withTrashed()->where('nis', '250001')->sole())
        ->trashed()->toBeTrue()
        ->nama_lengkap->toBe('Nama Lama');
});

it('rejects grades for a student that is not registered', function () {
    Kelas::factory()->create(['nama' => 'VII-A']);

    jalankanImport(ListRapors::class, 'importNilai', fileCsv([
        ['NIS', 'Tahun Ajaran', 'Semester (1/2)', 'Kelas pada Semester'],
        ['999999', '2025/2026', '1', 'VII-A'],
    ]));

    expect(Rapor::query()->count())->toBe(0);
});

it('creates students and their grades from the combined template', function () {
    Kelas::factory()->create(['nama' => 'VII-A']);
    MataPelajaran::factory()->create(['nama' => 'Matematika']);

    jalankanImport(ListSiswas::class, 'importGabungan', fileCsv([
        ['NIS', 'Nama Lengkap', 'Jenis Kelamin (L/P)', 'Kelas', 'Nama Ayah', 'Tahun Ajaran', 'Semester (1/2)', 'Matematika - Pengetahuan'],
        ['250001', 'Ahmad Fauzi', 'L', 'VII-A', 'Budi', '2025/2026', '1', '80'],
        ['250001', 'Ahmad Fauzi', 'L', 'VII-A', 'Budi', '2025/2026', '2', '90'],
        ['250002', 'Siti Rahma', 'P', 'VII-A', '', '', '', ''],
    ]));

    $ahmad = Siswa::query()->where('nis', '250001')->sole();

    expect(Siswa::query()->count())->toBe(2)
        ->and($ahmad->ayah->nama)->toBe('Budi')
        ->and($ahmad->rapor()->count())->toBe(2)
        ->and(Siswa::query()->where('nis', '250002')->sole()->rapor()->exists())->toBeFalse();
});

it('hides the grade import from users who cannot write grades', function () {
    actingAs(userWithPermissions(['ViewAny:Siswa', 'Create:Siswa', 'Update:Siswa']));

    Livewire::test(ListSiswas::class)
        ->assertActionVisible('importSiswa')
        ->assertActionHidden('importNilai')
        ->assertActionHidden('importGabungan');
});

it('offers one template per import type', function (string $aksi, string $namaFile) {
    Livewire::test(ListSiswas::class)
        ->mountAction($aksi)
        ->callAction('downloadTemplate')
        ->assertFileDownloaded($namaFile);
})->with([
    'data siswa' => ['importSiswa', 'template-data-siswa.xlsx'],
    'data nilai' => ['importNilai', 'template-data-nilai-siswa.xlsx'],
    'gabungan' => ['importGabungan', 'template-data-siswa-dan-nilai.xlsx'],
]);

it('rejects a row with guardian details but no guardian name', function () {
    jalankanImport(ListSiswas::class, 'importSiswa', fileCsv([
        ['NIS', 'Nama Lengkap', 'Jenis Kelamin (L/P)', 'Pekerjaan Wali'],
        ['250001', 'Ahmad Fauzi', 'L', 'Pedagang'],
    ]));

    expect(Siswa::query()->count())->toBe(0);
});

it('stores the guardian when the guardian name is given', function () {
    jalankanImport(ListSiswas::class, 'importSiswa', fileCsv([
        ['NIS', 'Nama Lengkap', 'Jenis Kelamin (L/P)', 'Nama Wali', 'Pekerjaan Wali'],
        ['250001', 'Ahmad Fauzi', 'L', 'Paman Ali', 'Pedagang'],
    ]));

    expect(Siswa::query()->sole()->wali)
        ->jenis->toBe(JenisOrangTua::Wali)
        ->pekerjaan->toBe('Pedagang');
});

/**
 * The rows of the first sheet of an .xlsx file, as lists of cell values.
 *
 * @return array<int, array<int, mixed>>
 */
function bacaXlsx(string $path): array
{
    $reader = new Reader;
    $reader->open($path);

    $baris = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $baris[] = $row->toArray();
        }

        break;
    }

    $reader->close();

    return $baris;
}

it('names the grade columns after the subjects and reads them back', function () {
    $siswa = Siswa::factory()->create(['nis' => '250001']);
    Kelas::factory()->create(['nama' => 'VII-A']);
    $matematika = MataPelajaran::factory()->create(['nama' => 'Matematika']);

    expect(array_map(fn ($kolom) => $kolom->getLabel(), NilaiImporter::getColumns()))
        ->toContain('Matematika (P)', 'Matematika (K)', 'Matematika (Sikap)');

    jalankanImport(ListRapors::class, 'importNilai', fileCsv([
        ['NIS', 'Tahun Ajaran', 'Semester (1/2)', 'Kelas pada Semester', 'Matematika (P)', 'Matematika (K)', 'Matematika (Sikap)'],
        ['250001', '2025/2026', '1', 'VII-A', '88', '84', 'sb'],
    ]));

    expect(Rapor::query()->whereBelongsTo($siswa)->sole()->nilaiRapor->sole())
        ->mata_pelajaran_id->toBe($matematika->id)
        ->nilai_pengetahuan->toBe(88)
        ->sikap->toBe('SB');
});

it('pre-fills the class grade template with the class members and the grades already entered', function () {
    $tahunAjaran = TahunAjaran::factory()->aktif()->create(['nama' => '2025/2026']);
    $kelas = Kelas::factory()->create(['nama' => 'VII-A']);
    $matematika = MataPelajaran::factory()->create(['nama' => 'Matematika']);
    $sudahDinilai = Siswa::factory()->for($kelas)->create(['nis' => '250001', 'nama_lengkap' => 'Ahmad']);
    Siswa::factory()->for($kelas)->create(['nis' => '250002', 'nama_lengkap' => 'Budi']);
    Siswa::factory()->for(Kelas::factory())->create(['nama_lengkap' => 'Kelas Lain']);
    $rapor = Rapor::factory()->for($sudahDinilai)->for($tahunAjaran)->for($kelas)->create(['semester' => Semester::Ganjil, 'sakit' => 2]);
    NilaiRapor::factory()->for($rapor)->for($matematika)->create(['nilai_pengetahuan' => 90, 'nilai_keterampilan' => 85, 'sikap' => 'B']);

    $baris = bacaXlsx(BukuIndukExport::templateNilaiKelas($kelas, $tahunAjaran, Semester::Ganjil));
    $isi = array_map(fn (array $nilai): array => array_combine($baris[0], array_pad($nilai, count($baris[0]), null)), array_slice($baris, 1));

    expect($isi)->toHaveCount(2)
        ->and($isi[0])->toMatchArray([
            'NIS' => '250001',
            'Nama Lengkap' => 'Ahmad',
            'Tahun Ajaran' => '2025/2026',
            'Semester (1/2)' => 1,
            'Kelas pada Semester' => 'VII-A',
            'Sakit (hari)' => 2,
            'Matematika (P)' => 90,
            'Matematika (K)' => 85,
            'Matematika (Sikap)' => 'B',
        ])
        ->and($isi[1])->toMatchArray([
            'NIS' => '250002',
            'Nama Lengkap' => 'Budi',
            'Tahun Ajaran' => '2025/2026',
            'Kelas pada Semester' => 'VII-A',
            'Matematika (P)' => '',
        ]);
});

it('downloads the class grade template or an empty one when no class is chosen', function () {
    $tahunAjaran = TahunAjaran::factory()->aktif()->create(['nama' => '2025/2026']);
    $kelas = Kelas::factory()->create(['nama' => 'VII-A']);

    Livewire::test(ListRapors::class)
        ->mountAction('importNilai')
        ->callAction('downloadTemplate', ['tahun_ajaran_id' => $tahunAjaran->id, 'semester' => Semester::Genap->value, 'kelas_id' => $kelas->id])
        ->assertFileDownloaded('template-nilai-vii-a-20252026-semester-genap.xlsx');
});
