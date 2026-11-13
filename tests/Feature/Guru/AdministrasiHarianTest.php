<?php

use App\Enums\Hari;
use App\Enums\JenisAgenda;
use App\Enums\Semester;
use App\Enums\StatusJadwal;
use App\Filament\Guru\Pages\BukuPiket;
use App\Filament\Guru\Pages\JurnalMengajar as HalamanJurnal;
use App\Filament\Guru\Pages\KehadiranGtk;
use App\Filament\Guru\Pages\PresensiKelas;
use App\Filament\Guru\Pages\RaporKelasSaya;
use App\Filament\Guru\Pages\RekapHarian;
use App\Filament\Guru\Widgets\AksiHarian;
use App\Filament\Guru\Widgets\JadwalHariIni;
use App\Models\Gtk;
use App\Models\IsiJadwal;
use App\Models\Jadwal;
use App\Models\JurnalMengajar;
use App\Models\KalenderAkademik;
use App\Models\Kelas;
use App\Models\PembagianMengajar;
use App\Models\PenugasanPiket;
use App\Models\PresensiGtk;
use App\Models\PresensiSiswa;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Services\Harian\PenyusunJurnalGemini;
use App\Services\Harian\PertemuanMengajar;
use App\Services\Harian\PresensiHarian;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-10 10:00:00', 'Asia/Jakarta'));
    Filament::setCurrentPanel('guru');
    $this->tahun = TahunAjaran::factory()->aktif()->create(['nama' => '2026/2027']);
    $this->gtk = Gtk::factory()->denganAkun()->create();
    $this->guru = $this->gtk->user;
    $this->kelas = Kelas::factory()->create(['wali_kelas_id' => $this->gtk->id]);
    $this->siswa = Siswa::factory()->for($this->kelas)->create();
    $this->presensi = app(PresensiHarian::class);
    $this->baris = [['siswa_id' => $this->siswa->id, 'status' => 'sakit', 'terlambat_menit' => 0, 'catatan' => null]];
    $this->simpan = function (array $baris, string $tanggal = '2026-11-10', ?string $alasan = null): void {
        $this->presensi->simpanKelas($this->guru, $this->kelas, $tanggal, $baris, $this->presensi->versi($this->kelas->id, $tanggal), $alasan);
    };
});

it('records daily attendance once and synchronizes existing and newly created report cards', function () {
    $rapor = Rapor::factory()->for($this->siswa)->for($this->kelas)->for($this->tahun, 'tahunAjaran')->create();
    ($this->simpan)($this->baris);
    ($this->simpan)($this->baris);
    expect(PresensiSiswa::count())->toBe(1)->and($rapor->refresh()->sakit)->toBe(1)->and($rapor->izin)->toBe(0);
    $this->baris[0]['status'] = 'izin';
    ($this->simpan)($this->baris, '2026-11-09', 'Mengisi catatan kehadiran kemarin');
    expect($rapor->refresh()->izin)->toBe(1);
    $rapor->forceDelete();
    $rapor = Rapor::factory()->for($this->siswa)->for($this->kelas)->for($this->tahun, 'tahunAjaran')->create();
    expect($rapor->sakit)->toBe(1)->and($rapor->izin)->toBe(1)->and($rapor->tanpa_keterangan)->toBe(0);
});

it('requires reasons for corrections and detects stale forms', function () {
    $version = $this->presensi->versi($this->kelas->id, '2026-11-10');
    ($this->simpan)($this->baris);
    $this->baris[0]['status'] = 'hadir';
    expect(fn () => ($this->simpan)($this->baris))->toThrow(ValidationException::class);
    expect(fn () => $this->presensi->simpanKelas($this->guru, $this->kelas, '2026-11-10', $this->baris, $version, 'Koreksi'))->toThrow(ValidationException::class);
    ($this->simpan)($this->baris, '2026-11-10', 'Surat sakit ternyata untuk tanggal lain');
    expect(PresensiSiswa::first()->status)->toBe('hadir');
});

it('freezes report attendance until an authorized reasoned reopening', function () {
    ($this->simpan)($this->baris);
    $rapor = Rapor::factory()->for($this->siswa)->for($this->kelas)->for($this->tahun, 'tahunAjaran')->create();
    $this->presensi->kunciRapor($this->guru, $rapor, true);
    $this->baris[0]['status'] = 'izin';
    ($this->simpan)($this->baris, '2026-11-10', 'Koreksi surat izin');
    expect($rapor->refresh()->sakit)->toBe(1)->and($rapor->izin)->toBe(0);
    expect(fn () => $this->presensi->kunciRapor($this->guru, $rapor, false))->toThrow(ValidationException::class);
    $this->presensi->kunciRapor($this->guru, $rapor, false, 'Persetujuan perbaikan rapor');
    expect($rapor->refresh()->sakit)->toBe(0)->and($rapor->izin)->toBe(1);
});

it('rejects unauthorized teachers and foreign students', function () {
    $other = Gtk::factory()->denganAkun()->create();
    expect(fn () => $this->presensi->simpanKelas($other->user, $this->kelas, '2026-11-10', $this->baris, ''))->toThrow(HttpException::class);
    $this->baris[0]['siswa_id'] = Siswa::factory()->create()->id;
    expect(fn () => ($this->simpan)($this->baris))->toThrow(ValidationException::class);
    expect(PresensiSiswa::count())->toBe(0);
});

it('rejects future dates, Sundays and academic holidays', function () {
    expect(fn () => ($this->simpan)($this->baris, '2026-11-11'))->toThrow(ValidationException::class);
    expect(fn () => ($this->simpan)($this->baris, '2026-11-08'))->toThrow(ValidationException::class);
    KalenderAkademik::factory()->for($this->tahun, 'tahunAjaran')->create(['jenis' => JenisAgenda::Libur, 'tanggal_mulai' => '2026-11-10', 'tanggal_selesai' => '2026-11-10']);
    expect(fn () => ($this->simpan)($this->baris))->toThrow(ValidationException::class);
});

it('lets assigned duty teachers record tardiness without counting it as absence', function () {
    $piket = Gtk::factory()->denganAkun()->create();
    PenugasanPiket::create(['tanggal' => '2026-11-10', 'gtk_id' => $piket->id, 'pencatat_id' => $this->guru->id]);
    $data = ['jenis' => 'terlambat', 'kelas_id' => $this->kelas->id, 'siswa_id' => $this->siswa->id, 'terlambat_menit' => 15, 'kejadian' => str_repeat('Terlambat karena perjalanan. ', 30)];
    $this->presensi->piket($piket->user, '2026-11-10', $data);
    expect(PresensiSiswa::first()->status)->toBe('hadir')->and(PresensiSiswa::first()->terlambat_menit)->toBe(15);
    expect(fn () => $this->presensi->piket($piket->user, '2026-11-09', $data))->toThrow(HttpException::class);
});

function siapkanPertemuanHarian($test): string
{
    $jadwal = Jadwal::factory()->for($test->tahun, 'tahunAjaran')->create(['status' => StatusJadwal::Terbit]);
    $assignment = PembagianMengajar::factory()->for($test->tahun, 'tahunAjaran')->for($test->kelas)->for($test->gtk)->create();
    foreach ([1, 2, 5] as $jp) {
        IsiJadwal::factory()->for($jadwal)->for($assignment, 'pembagianMengajar')->create(['hari' => Hari::Selasa, 'jp_ke' => $jp]);
    }

    return $jadwal->id.':'.$assignment->id.':1';
}

it('groups consecutive lessons and keeps teaching attendance idempotent and separate from the journal', function () {
    $key = siapkanPertemuanHarian($this);
    $service = app(PertemuanMengajar::class);
    expect($service->daftar($this->guru, '2026-11-10'))->toHaveCount(2);
    $service->simpan($this->guru, '2026-11-10', $key, [], true);
    $service->simpan($this->guru, '2026-11-10', $key, [], true);
    expect(JurnalMengajar::count())->toBe(1)->and(JurnalMengajar::first()->status)->toBe('hadir')->and(PresensiGtk::count())->toBe(1);
    $service->simpan($this->guru, '2026-11-10', $key, ['status' => 'terlaksana', 'materi' => 'Pecahan', 'kegiatan' => 'Latihan bersama']);
    expect(JurnalMengajar::first()->status)->toBe('terlaksana');
    expect(fn () => $service->simpan($this->guru, '2026-11-10', $key, ['materi' => 'Berubah']))->toThrow(ValidationException::class);
});

it('records substitute teachers without marking the original teacher present', function () {
    $key = siapkanPertemuanHarian($this);
    $piket = Gtk::factory()->denganAkun()->create();
    PenugasanPiket::create(['tanggal' => '2026-11-10', 'gtk_id' => $piket->id, 'pencatat_id' => $this->guru->id]);
    app(PertemuanMengajar::class)->simpan($piket->user, '2026-11-10', $key, ['status' => 'digantikan', 'pelaksana_id' => $piket->id, 'materi' => 'Latihan pecahan', 'alasan' => 'Guru berhalangan hadir']);
    expect(JurnalMengajar::first()->pelaksana_id)->toBe($piket->id)->and(PresensiGtk::where('gtk_id', $this->gtk->id)->exists())->toBeFalse();
});

it('keeps reasons for retrospective teaching attendance without manufacturing daily GTK attendance', function () {
    $key = siapkanPertemuanHarian($this);
    $this->travelTo(Carbon::parse('2026-11-11 10:00:00', 'Asia/Jakarta'));
    $service = app(PertemuanMengajar::class);
    expect(fn () => $service->simpan($this->guru, '2026-11-10', $key, [], true))->toThrow(ValidationException::class);
    $record = $service->simpan($this->guru, '2026-11-10', $key, ['alasan' => 'Kemarin koneksi terputus'], true);
    expect($record->alasan)->toBe('Kemarin koneksi terputus')->and(PresensiGtk::count())->toBe(0);
});

it('renders the attendance form and saves through the visible action', function () {
    actingAs($this->guru);
    Livewire::test(PresensiKelas::class)->assertSuccessful()->callAction('hadirSemua')->callAction('simpan')->assertHasNoFormErrors();
    expect(PresensiSiswa::first()->status)->toBe('hadir');
});

it('renders daily pages and opens the journal action', function () {
    $key = siapkanPertemuanHarian($this);
    actingAs($this->guru);
    Livewire::test(HalamanJurnal::class)->assertSuccessful()->mountAction(TestAction::make('isi')->table($key))->assertActionMounted(TestAction::make('isi')->table($key));
    Livewire::test(KehadiranGtk::class)->assertSuccessful()->callAction('hadir');
    Livewire::test(RekapHarian::class)->assertSuccessful();
    PenugasanPiket::create(['tanggal' => '2026-11-10', 'gtk_id' => $this->gtk->id, 'pencatat_id' => $this->guru->id]);
    Livewire::test(BukuPiket::class)->assertSuccessful();
});

it('prints real attendance and prevents another teacher from exporting it', function () {
    ($this->simpan)($this->baris);
    $params = ['jenis' => 'siswa', 'mulai' => '2026-11-01', 'selesai' => '2026-11-10', 'kelas_id' => $this->kelas->id];
    actingAs($this->guru)->get(route('harian.cetak', $params))->assertOk()->assertSee($this->siswa->nama_lengkap);
    actingAs($this->guru)->get(route('harian.cetak', [...$params, 'format' => 'pdf']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    actingAs(Gtk::factory()->denganAkun()->create()->user)->get(route('harian.cetak', $params))->assertForbidden();
});

it('uses Gemini for reviewed drafts only, masks known student details and reuses identical requests', function () {
    config(['services.gemini.key' => 'test', 'services.gemini.model' => 'test-model']);
    Http::preventStrayRequests();
    $draft = json_encode(['materi' => 'Pecahan', 'kegiatan' => 'Latihan', 'refleksi' => '', 'tindak_lanjut' => 'Usulan: latihan lanjutan']);
    Http::fake(['*' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => $draft]]]]],
    ])]);
    $notes = 'Belajar pecahan dengan '.$this->siswa->nama_lengkap.' NISN '.$this->siswa->nisn;
    $service = app(PenyusunJurnalGemini::class);
    $result = $service->susun($this->guru->id, $this->kelas->id, 'Matematika', $notes);
    $service->susun($this->guru->id, $this->kelas->id, 'Matematika', $notes);
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => ! str_contains($request->body(), $this->siswa->nama_lengkap) && ! str_contains($request->body(), $this->siswa->nisn));
    expect($result['materi'])->toBe('Pecahan')->and(JurnalMengajar::count())->toBe(0);
});

it('generates a Gemini draft from the journal form and saves only after teacher review', function () {
    $key = siapkanPertemuanHarian($this);
    config(['services.gemini.key' => 'test', 'services.gemini.model' => 'test-model']);
    $draft = json_encode(['materi' => 'Pecahan', 'kegiatan' => 'Latihan', 'refleksi' => '', 'tindak_lanjut' => 'Usulan: latihan']);
    Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => $draft]]]]]])]);
    actingAs($this->guru);
    $parent = TestAction::make('isi')->table($key);
    $page = Livewire::test(HalamanJurnal::class)->mountAction($parent)
        ->fillForm(['catatan_asli' => 'Latihan pecahan'], 'mountedActionSchema0')
        ->callAction(TestAction::make('rapikan')->schemaComponent('catatan_asli', 'mountedActionSchema0'));
    Http::assertSentCount(1);
    expect(JurnalMengajar::count())->toBe(0);
    $page->callMountedAction()->assertHasNoFormErrors();
    expect(JurnalMengajar::first()->materi)->toBe('Pecahan')->and(JurnalMengajar::first()->dibantu_ai)->toBeTrue();
});

it('does not fabricate or save journals when Gemini returns invalid content', function () {
    config(['services.gemini.key' => 'test', 'services.gemini.model' => 'test-model']);
    Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"materi":42}']]]]]])]);
    expect(fn () => app(PenyusunJurnalGemini::class)->susun($this->guru->id, $this->kelas->id, 'Matematika', 'Latihan pecahan'))->toThrow(RuntimeException::class);
    expect(JurnalMengajar::count())->toBe(0);
});

it('restricts GTK exports, validates report periods and prints other report types', function () {
    $other = Gtk::factory()->denganAkun()->create();
    $params = ['jenis' => 'gtk', 'mulai' => '2026-11-01', 'selesai' => '2026-11-10'];
    actingAs($this->guru)->get(route('harian.cetak', [...$params, 'gtk_id' => $other->id]))->assertForbidden();
    foreach (['gtk', 'jurnal', 'piket'] as $jenis) {
        actingAs($this->guru)->get(route('harian.cetak', [...$params, 'jenis' => $jenis]))->assertOk();
    }
    actingAs($this->guru)->getJson(route('harian.cetak', [...$params, 'mulai' => '2020-01-01']))->assertUnprocessable();
});

it('isolates semester totals and prevents manual edits of locked report attendance', function () {
    ($this->simpan)($this->baris);
    $rapor = Rapor::factory()->for($this->siswa)->for($this->kelas)->for($this->tahun, 'tahunAjaran')->create(['semester' => Semester::Genap, 'sakit' => 3]);
    expect($rapor->sakit)->toBe(3);
    $ganjil = Rapor::factory()->for($this->siswa)->for($this->kelas)->for($this->tahun, 'tahunAjaran')->create(['semester' => Semester::Ganjil]);
    $this->presensi->kunciRapor($this->guru, $ganjil, true);
    expect(fn () => $ganjil->fill(['sakit' => 99])->save())->toThrow(ValidationException::class);
    expect($ganjil->fresh()->sakit)->toBe(1);
});

it('restricts assignment management and opens admin daily pages for authorized managers', function () {
    $manager = userWithPermissions(['View:PenugasanPiket']);
    Filament::setCurrentPanel('admin');
    actingAs($this->guru);
    Livewire::test(App\Filament\Pages\PenugasanPiket::class)->assertForbidden();
    actingAs($manager);
    Livewire::test(App\Filament\Pages\PenugasanPiket::class)->assertSuccessful()->callAction('tugaskan', ['tanggal' => '2026-11-10', 'gtk_id' => $this->gtk->id, 'catatan' => 'Piket Selasa'])->assertHasNoFormErrors();
    Livewire::test(PresensiKelas::class)->assertSuccessful();
    Livewire::test(HalamanJurnal::class)->assertSuccessful();
    Livewire::test(KehadiranGtk::class)->assertSuccessful();
    Livewire::test(BukuPiket::class)->assertSuccessful();
    expect(PenugasanPiket::count())->toBe(1);
});

it('locks and reopens report attendance from the teacher table actions', function () {
    ($this->simpan)($this->baris);
    $rapor = Rapor::factory()->for($this->siswa)->for($this->kelas)->for($this->tahun, 'tahunAjaran')->create();
    actingAs($this->guru);
    Livewire::test(RaporKelasSaya::class)
        ->callAction(TestAction::make('kunciPresensi')->table($this->siswa))->assertHasNoActionErrors();
    expect($rapor->refresh()->presensi_dikunci_at)->not->toBeNull();
    Livewire::test(RaporKelasSaya::class)
        ->callAction(TestAction::make('bukaPresensi')->table($this->siswa), ['alasan' => 'Pemeriksaan ulang rekap'])->assertHasNoActionErrors();
    expect($rapor->refresh()->presensi_dikunci_at)->toBeNull();
});

it('records attendance from the dashboard shortcut and renders daily shortcut cards', function () {
    $key = siapkanPertemuanHarian($this);
    actingAs($this->guru);
    Livewire::test(JadwalHariIni::class)->assertSuccessful()
        ->callAction(TestAction::make('hadir')->table($key))->assertHasNoActionErrors();
    Livewire::test(AksiHarian::class)->assertSuccessful()->assertSee('Hadir');
    expect(JurnalMengajar::count())->toBe(1)->and(PresensiGtk::count())->toBe(1);
});
