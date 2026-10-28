<?php

use App\Enums\JenisMutasi;
use App\Enums\JenisOrangTua;
use App\Enums\Semester;
use App\Filament\Resources\Siswas\Pages\ListSiswas;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\MutasiSiswa;
use App\Models\NilaiRapor;
use App\Models\OrangTua;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    config(['sekolah.nama' => 'MTs At-Taufiq Cisurupan', 'sekolah.tempat_tanda_tangan' => 'Cisurupan']);
    $this->tahunAjaran = TahunAjaran::factory()->merdeka()->aktif()->create(['nama' => '2026/2027', 'kepala_madrasah' => 'Drs. H. Ahmad Syafei']);
    $this->kelas = Kelas::factory()->create(['tingkat' => 8, 'nama' => 'VIII-A']);
    $this->siswa = Siswa::factory()->for($this->kelas)->create(['nis' => '250001', 'nama_lengkap' => 'Ahmad Fauzi']);
    OrangTua::factory()->for($this->siswa)->create(['jenis' => JenisOrangTua::Ayah, 'nama' => 'Budi Santoso']);
});

it('prints the proof of enrolment with the number, purpose and principal', function () {
    actingAs(userWithPermissions(['View:Siswa']));

    get(route('surat.keterangan-aktif', ['siswa' => $this->siswa, 'nomor' => 'B-12/MTs/2026', 'keperluan' => 'pengajuan beasiswa PIP', 'tanggal' => '2026-10-28']))
        ->assertOk()
        ->assertSeeText([
            'SURAT KETERANGAN AKTIF', 'Nomor: B-12/MTs/2026', 'Ahmad Fauzi', '250001', 'VIII-A', 'Budi Santoso',
            'Tahun Pelajaran 2026/2027 Semester 1 (Ganjil)', 'keperluan pengajuan beasiswa PIP', 'Cisurupan, 28 Oktober 2026', 'Drs. H. Ahmad Syafei',
        ]);

    expect(Activity::query()->where('event', 'surat_dicetak')->sole()->properties->all())->toBe(['jenis' => 'keterangan_aktif', 'nomor' => 'B-12/MTs/2026']);
});

it('downloads a letter as PDF', function () {
    actingAs(userWithPermissions(['View:Siswa']));

    get(route('surat.keterangan-aktif', ['siswa' => $this->siswa, 'format' => 'pdf']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertDownload('surat-keterangan-aktif-250001-ahmad-fauzi.pdf');
});

it('prints the transfer letter with the destination school', function () {
    actingAs(userWithPermissions(['View:MutasiSiswa']));
    $mutasi = MutasiSiswa::factory()->for($this->siswa)->for($this->kelas)->for($this->tahunAjaran)->create([
        'jenis' => JenisMutasi::Pindah,
        'tanggal' => '2026-11-03',
        'sekolah' => 'MTs Negeri 1 Garut',
        'alasan' => 'mengikuti orang tua',
        'nomor_surat' => 'B-45/MTs/2026',
    ]);

    get(route('surat.pindah', $mutasi))
        ->assertOk()
        ->assertSeeText(['SURAT KETERANGAN PINDAH SEKOLAH', 'Nomor: B-45/MTs/2026', 'Ahmad Fauzi', '3 November 2026', 'MTs Negeri 1 Garut', 'mengikuti orang tua']);
});

it('lists every semester in the transcript, scoring each by its own curriculum', function () {
    actingAs(userWithPermissions(['View:Siswa', 'ViewAny:Rapor']));
    $matematika = MataPelajaran::factory()->create(['nama' => 'Matematika']);
    $tahunK13 = TahunAjaran::factory()->create(['nama' => '2025/2026']);
    $kelasTujuh = Kelas::factory()->create(['tingkat' => 7]);

    $raporK13 = Rapor::factory()->for($this->siswa)->for($tahunK13)->for($kelasTujuh)->create(['semester' => Semester::Genap]);
    NilaiRapor::factory()->for($raporK13)->for($matematika)->create(['nilai_pengetahuan' => 80, 'nilai_keterampilan' => 90]);
    $raporMerdeka = Rapor::factory()->for($this->siswa)->for($this->tahunAjaran)->for($this->kelas)->create(['semester' => Semester::Ganjil]);
    NilaiRapor::factory()->for($raporMerdeka)->for($matematika)->create(['nilai_akhir' => 95, 'nilai_pengetahuan' => null, 'nilai_keterampilan' => null]);

    get(route('surat.transkrip', $this->siswa))
        ->assertOk()
        ->assertSeeTextInOrder(['VII / 2', 'VIII / 1', '2025/2026', '2026/2027', 'Matematika', '85', '95', '90,00'])
        ->assertSeeText('Rata-rata Keseluruhan');
});

it('forbids letters without permission to view the student', function (string $route) {
    $parameter = $route === 'surat.pindah' ? MutasiSiswa::factory()->for($this->siswa)->create() : $this->siswa;

    actingAs(User::factory()->create())
        ->get(route($route, $parameter))
        ->assertForbidden();
})->with(['surat.keterangan-aktif', 'surat.transkrip', 'surat.pindah']);

it('opens the letter from the student table, as a print link or a PDF download', function () {
    actingAs(userWithPermissions(['ViewAny:Siswa', 'View:Siswa']));

    Livewire::test(ListSiswas::class)
        ->callAction(TestAction::make('suratKeteranganAktif')->table($this->siswa), ['nomor' => 'B-1/2026', 'keperluan' => 'lomba', 'tanggal' => '2026-10-28', 'mode' => 'cetak'])
        ->assertNotified('Surat Keterangan Aktif siap dicetak');

    Livewire::test(ListSiswas::class)
        ->callAction(TestAction::make('suratKeteranganAktif')->table($this->siswa), ['tanggal' => '2026-10-28', 'mode' => 'unduh'])
        ->assertRedirect(route('surat.keterangan-aktif', ['siswa' => $this->siswa, 'tanggal' => '2026-10-28', 'format' => 'pdf']));
});
