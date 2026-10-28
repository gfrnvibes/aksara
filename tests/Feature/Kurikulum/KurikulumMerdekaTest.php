<?php

use App\Enums\KelompokMapel;
use App\Enums\KeputusanKenaikan;
use App\Enums\Semester;
use App\Exports\BukuIndukExport;
use App\Filament\Pages\InputNilaiKelas;
use App\Filament\Resources\Rapors\Pages\CreateRapor;
use App\Models\Ekstrakurikuler;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\NilaiEkstrakurikuler;
use App\Models\NilaiRapor;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    $this->tahunAjaran = TahunAjaran::factory()->merdeka()->aktif()->create(['nama' => '2026/2027']);
    $this->kelas = Kelas::factory()->create(['tingkat' => 7, 'nama' => 'VII-A']);
    $this->matematika = MataPelajaran::factory()->create(['nama' => 'Matematika', 'kkm' => 75, 'kelompok' => KelompokMapel::A]);
    $this->siswa = Siswa::factory()->for($this->kelas)->create(['nis' => '260001', 'nama_lengkap' => 'Ahmad Fauzi']);
});

it('grades a Kurikulum Merdeka report card with a final score and the competence reached', function () {
    actingAs(userWithPermissions(['ViewAny:Rapor', 'Create:Rapor']));

    Livewire::test(CreateRapor::class)
        ->fillForm([
            'siswa_id' => $this->siswa->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'semester' => Semester::Ganjil->value,
            'kelas_id' => $this->kelas->id,
        ])
        ->assertSchemaComponentHidden('nilai_akhlak')
        ->fillForm([
            'nilaiRapor' => [[
                'mata_pelajaran_id' => $this->matematika->id,
                'kkm' => 75,
                'nilai_akhir' => 88,
                'capaian_kompetensi' => 'Menunjukkan penguasaan yang baik dalam operasi bilangan bulat.',
            ]],
            'nilaiEkstrakurikuler' => [],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(NilaiRapor::query()->sole())
        ->nilai_akhir->toBe(88)
        ->capaian_kompetensi->toBe('Menunjukkan penguasaan yang baik dalam operasi bilangan bulat.')
        ->nilai_pengetahuan->toBeNull()
        ->predikat_pengetahuan->toBeNull();
});

it('enters a Kurikulum Merdeka class grade with its final score and description', function () {
    actingAs(userWithPermissions([InputNilaiKelas::IZIN]));

    $halaman = Livewire::test(InputNilaiKelas::class)->fillForm([
        'tahun_ajaran_id' => $this->tahunAjaran->id,
        'semester' => Semester::Ganjil->value,
        'kelas_id' => $this->kelas->id,
        'mata_pelajaran_id' => $this->matematika->id,
    ]);
    $kunci = array_key_first($halaman->get('data.nilai'));

    $halaman
        ->set("data.nilai.{$kunci}.nilai_akhir", 91)
        ->set("data.nilai.{$kunci}.capaian_kompetensi", 'Menunjukkan penguasaan yang sangat baik dalam aljabar.')
        ->callAction('simpanNilai')
        ->assertNotified('Nilai tersimpan');

    expect(NilaiRapor::query()->sole())
        ->nilai_akhir->toBe(91)
        ->capaian_kompetensi->toBe('Menunjukkan penguasaan yang sangat baik dalam aljabar.');
});

it('builds the class grade template with only the Kurikulum Merdeka columns', function () {
    $path = BukuIndukExport::templateNilaiKelas($this->kelas, $this->tahunAjaran, Semester::Ganjil);

    $reader = new Reader;
    $reader->open($path);
    $judul = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $judul = $row->toArray();

            break;
        }
    }

    $reader->close();

    expect($judul)->toContain('Matematika (Nilai Akhir)', 'Matematika (Capaian Kompetensi)')
        ->not->toContain('Matematika (P)')
        ->not->toContain('Matematika (Sikap)')
        ->not->toContain('Akhlak');
});

it('prints a Kurikulum Merdeka report card in the RDM layout', function () {
    actingAs(userWithPermissions(['View:Rapor']));
    $this->tahunAjaran->update(['kepala_madrasah' => 'Drs. H. Ahmad Syafei']);
    $rapor = Rapor::factory()->for($this->siswa)->for($this->tahunAjaran)->for($this->kelas)->create([
        'semester' => Semester::Genap,
        'keputusan' => KeputusanKenaikan::Naik,
        'tanpa_keterangan' => 2,
        'nilai_akhlak' => 'A',
    ]);
    NilaiRapor::factory()->for($rapor)->for($this->matematika)->create([
        'nilai_akhir' => 86,
        'capaian_kompetensi' => 'Menunjukkan penguasaan yang baik dalam statistika.',
        'nilai_pengetahuan' => null,
        'nilai_keterampilan' => null,
    ]);
    NilaiEkstrakurikuler::factory()->for($rapor)->for(Ekstrakurikuler::factory()->create(['nama' => 'Pramuka']))->create(['nilai' => 'B']);

    get(route('rapor.cetak', $rapor))
        ->assertOk()
        ->assertSeeText([
            'CAPAIAN HASIL BELAJAR', 'Fase', 'D', 'Kelompok Mata Pelajaran Umum', 'Matematika', '86',
            'Menunjukkan penguasaan yang baik dalam statistika.', 'Jumlah', 'Pramuka', 'Baik', 'Alpa',
            'Tanggapan Orang Tua/Wali', 'Naik ke kelas VIII', 'Drs. H. Ahmad Syafei',
        ])
        ->assertDontSeeText(['Akhlak dan Kepribadian', 'Pengetahuan']);
});

it('prints a K13 report card with knowledge and skill scores', function () {
    actingAs(userWithPermissions(['View:Rapor']));
    $tahunK13 = TahunAjaran::factory()->create(['nama' => '2022/2023']);
    $rapor = Rapor::factory()->for($this->siswa)->for($tahunK13)->for($this->kelas)->create(['semester' => Semester::Ganjil]);
    NilaiRapor::factory()->for($rapor)->for($this->matematika)->create(['nilai_pengetahuan' => 90, 'nilai_keterampilan' => 84]);

    get(route('rapor.cetak', $rapor))
        ->assertOk()
        ->assertSeeText(['Pengetahuan', 'Keterampilan', '90', '84', 'Akhlak dan Kepribadian'])
        ->assertDontSeeText('Capaian Kompetensi');
});

it('downloads the report card as PDF', function () {
    actingAs(userWithPermissions(['View:Rapor']));
    $rapor = Rapor::factory()->for($this->siswa)->for($this->tahunAjaran)->for($this->kelas)->create(['semester' => Semester::Ganjil]);

    get(route('rapor.cetak', ['rapor' => $rapor, 'format' => 'pdf']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertDownload('rapor-260001-ahmad-fauzi-20262027-semester-ganjil.pdf');
});

it('forbids printing a report card without permission to view it', function () {
    $rapor = Rapor::factory()->for($this->siswa)->for($this->tahunAjaran)->for($this->kelas)->create();

    actingAs(User::factory()->create())
        ->get(route('rapor.cetak', $rapor))
        ->assertForbidden();
});

it('shows the final score per semester on the Buku Induk grade sheet', function () {
    actingAs(userWithPermissions(['View:Siswa', 'ViewAny:Rapor']));
    $rapor = Rapor::factory()->for($this->siswa)->for($this->tahunAjaran)->for($this->kelas)->create(['semester' => Semester::Ganjil]);
    NilaiRapor::factory()->for($rapor)->for($this->matematika)->create(['nilai_akhir' => 79, 'nilai_pengetahuan' => null, 'nilai_keterampilan' => null]);

    get(route('siswa.cetak', ['siswa' => $this->siswa, 'isi' => 'nilai']))
        ->assertOk()
        ->assertSeeText(['KKTP', 'Nilai Akhir', '79'])
        ->assertDontSeeText('Keterampilan');
});
