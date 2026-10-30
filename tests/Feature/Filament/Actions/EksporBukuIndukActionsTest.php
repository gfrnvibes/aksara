<?php

use App\Enums\IsiDokumen;
use App\Enums\JenisOrangTua;
use App\Enums\Semester;
use App\Exports\BukuIndukExport;
use App\Filament\Actions\EksporBukuIndukActions;
use App\Filament\Resources\Rapors\Pages\ListRapors;
use App\Filament\Resources\Siswas\Pages\ListSiswas;
use App\Models\Ekstrakurikuler;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\NilaiEkstrakurikuler;
use App\Models\NilaiRapor;
use App\Models\OrangTua;
use App\Models\Rapor;
use App\Models\Siswa;
use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Livewire\Notifications;
use Filament\Notifications\Notification;
use Filament\Notifications\NotificationMailbox;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * The link in the "Halaman cetak siap" notification sent by the last action.
 */
function urlHalamanCetak(): string
{
    app(NotificationMailbox::class)->flush();

    $notifications = new Notifications;
    $notifications->mount();
    $notifications->pullNotificationsFromSession();

    $siap = $notifications->notifications->first(fn (Notification $notification): bool => $notification->getTitle() === 'Halaman cetak siap');

    expect($siap)->not->toBeNull();

    return $siap->getActions()[0]->getUrl();
}

beforeEach(function () {
    $this->freezeTime();

    // A fresh upload folder per test: reusing one races Windows' delayed directory deletion.
    config(['livewire.temporary_file_upload.directory' => 'livewire-tmp-'.Str::lower(Str::random(8))]);

    actingAs(userWithPermissions([
        'ViewAny:Siswa', 'View:Siswa', 'Create:Siswa', 'Update:Siswa',
        'ViewAny:Rapor', 'View:Rapor', 'Create:Rapor', 'Update:Rapor',
    ]));
});

it('downloads the export in the chosen format', function (string $isi, string $format) {
    Siswa::factory()->create();

    Livewire::test(ListSiswas::class)
        ->callAction('exportSiswa', ['isi' => $isi, 'format' => $format])
        ->assertHasNoFormErrors()
        ->assertFileDownloaded(BukuIndukExport::namaFile('export', IsiDokumen::from($isi), $format));
})->with([
    'siswa excel' => ['siswa', 'xlsx'],
    'nilai excel' => ['nilai', 'xlsx'],
    'keduanya excel' => ['keduanya', 'xlsx'],
    'siswa pdf' => ['siswa', 'pdf'],
    'nilai pdf' => ['nilai', 'pdf'],
    'keduanya pdf' => ['keduanya', 'pdf'],
]);

it('exports a file that imports back into the same students and grades', function () {
    $kelas = Kelas::factory()->create(['nama' => 'VIII-B']);
    $matematika = MataPelajaran::factory()->create(['nama' => 'Matematika']);
    $pramuka = Ekstrakurikuler::factory()->create(['nama' => 'Pramuka']);
    $siswa = Siswa::factory()->for($kelas)->create(['nis' => '250001', 'nisn' => '0012345678', 'nama_lengkap' => 'Ahmad Fauzi']);
    OrangTua::factory()->for($siswa)->create(['jenis' => JenisOrangTua::Ibu, 'nama' => 'Siti Aminah']);
    $rapor = Rapor::factory()->for($siswa)->for($kelas)->create(['semester' => Semester::Genap, 'sakit' => 3]);
    NilaiRapor::factory()->for($rapor)->for($matematika)->create(['nilai_pengetahuan' => 87, 'nilai_keterampilan' => 78]);
    NilaiEkstrakurikuler::factory()->for($rapor)->for($pramuka)->create(['nilai' => 'B']);
    $tahunAjaran = $rapor->tahunAjaran->nama;

    $path = BukuIndukExport::excelSiswa(BukuIndukExport::muatSiswa(Siswa::query()), IsiDokumen::Keduanya);
    $siswa->forceDelete();

    Livewire::test(ListSiswas::class)
        ->mountAction('importGabungan')
        ->fillForm(['file' => UploadedFile::fake()->createWithContent('export.xlsx', file_get_contents($path))])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $diimpor = Siswa::query()->where('nis', '250001')->sole();
    $raporDiimpor = $diimpor->rapor()->sole();

    expect($diimpor)
        ->nisn->toBe('0012345678')
        ->nama_lengkap->toBe('Ahmad Fauzi')
        ->kelas_id->toBe($kelas->id)
        ->and($diimpor->ibu->nama)->toBe('Siti Aminah')
        ->and($raporDiimpor)
        ->semester->toBe(Semester::Genap)
        ->sakit->toBe(3)
        ->and($raporDiimpor->tahunAjaran->nama)->toBe($tahunAjaran)
        ->and($raporDiimpor->nilaiRapor->sole())
        ->nilai_pengetahuan->toBe(87)
        ->nilai_keterampilan->toBe(78)
        ->and($raporDiimpor->nilaiEkstrakurikuler->sole()->nilai)->toBe('B');
});

it('prints exactly the students matching the table filter', function () {
    $kelas = Kelas::factory()->create();
    $diKelas = Siswa::factory()->for($kelas)->create();
    $diLuarKelas = Siswa::factory()->create();

    Livewire::test(ListSiswas::class)
        ->filterTable('kelas', $kelas->id)
        ->callAction('cetakBukuInduk', ['isi' => 'siswa', 'mode' => 'cetak']);

    $this->get(urlHalamanCetak())
        ->assertOk()
        ->assertSeeText($diKelas->nama_lengkap)
        ->assertDontSeeText($diLuarKelas->nama_lengkap);
});

it('prints the selected students in the browser', function () {
    $terpilih = Siswa::factory()->count(2)->create();
    $lain = Siswa::factory()->create(['nama_lengkap' => 'Tidak Dipilih']);

    Livewire::test(ListSiswas::class)
        ->selectTableRecords($terpilih->modelKeys())
        ->callAction(TestAction::make('cetakBukuIndukTerpilih')->table()->bulk(), ['isi' => 'siswa', 'mode' => 'cetak']);

    $this->get(urlHalamanCetak())
        ->assertOk()
        ->assertSeeText($terpilih->pluck('nama_lengkap')->all())
        ->assertDontSeeText($lain->nama_lengkap)
        ->assertDontSeeText('DATA NILAI SISWA');
});

it('downloads the selected students as one PDF', function () {
    $terpilih = Siswa::factory()->count(2)->create();

    Livewire::test(ListSiswas::class)
        ->selectTableRecords($terpilih->modelKeys())
        ->callAction(TestAction::make('cetakBukuIndukTerpilih')->table()->bulk(), ['isi' => 'keduanya', 'mode' => 'unduh'])
        ->assertFileDownloaded(BukuIndukExport::namaFile('buku-induk', IsiDokumen::Keduanya, 'pdf'));
});

it('refuses a PDF download above the per-file limit', function () {
    $ids = range(1, EksporBukuIndukActions::MAKS_SISWA_PDF_BUKU_INDUK + 1);

    expect(EksporBukuIndukActions::prosesCetak($ids, IsiDokumen::Siswa, EksporBukuIndukActions::MODE_UNDUH))->toBeNull();

    Livewire::test(ListSiswas::class)->assertNotified('Terlalu banyak siswa untuk satu file PDF');
});

it('exports the filtered report cards from the grade list', function () {
    Rapor::factory()->create();

    Livewire::test(ListRapors::class)
        ->callAction('exportRapor', ['format' => 'xlsx'])
        ->assertFileDownloaded(BukuIndukExport::namaFile('export', IsiDokumen::Nilai, 'xlsx'));
});

it('does not offer grade content to users who cannot see grades', function () {
    actingAs(userWithPermissions(['ViewAny:Siswa', 'View:Siswa']));
    Siswa::factory()->create();

    Livewire::test(ListSiswas::class)
        ->callAction('exportSiswa', ['isi' => 'keduanya', 'format' => 'xlsx'])
        ->assertHasFormErrors(['isi'])
        ->assertNoFileDownloaded();
});
