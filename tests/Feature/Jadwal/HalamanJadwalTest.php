<?php

use App\Enums\Hari;
use App\Enums\JenisPermintaanJadwal;
use App\Enums\Semester;
use App\Enums\SifatPermintaanJadwal;
use App\Enums\StatusJadwal;
use App\Filament\Guru\Pages\JadwalSaya;
use App\Filament\Guru\Resources\PermintaanJadwals\Pages\ManagePermintaanJadwalSaya;
use App\Filament\Guru\Widgets\AgendaMadrasah;
use App\Filament\Guru\Widgets\JadwalHariIni;
use App\Filament\Resources\Jadwals\JadwalResource;
use App\Filament\Resources\Jadwals\Pages\AturJadwal;
use App\Filament\Resources\JamPelajarans\JamPelajaranResource;
use App\Filament\Resources\JamPelajarans\Pages\ManageJamPelajarans;
use App\Filament\Resources\KalenderAkademiks\KalenderAkademikResource;
use App\Filament\Resources\PermintaanJadwals\PermintaanJadwalResource;
use App\Filament\Resources\Siswas\SiswaResource;
use App\Models\Gtk;
use App\Models\IsiJadwal;
use App\Models\Jadwal;
use App\Models\JamPelajaran;
use App\Models\KalenderAkademik;
use App\Models\Kelas;
use App\Models\PembagianMengajar;
use App\Models\PermintaanJadwal;
use App\Models\TahunAjaran;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    JamPelajaran::isiBawaan();

    $this->tahunAjaran = TahunAjaran::factory()->aktif()->create();
    $this->jadwal = Jadwal::factory()->for($this->tahunAjaran)->create(['semester' => Semester::berdasarkanTanggal(now())]);
    $this->guru = Gtk::factory()->denganAkun()->create(['nama' => 'Bu Siti']);
    [$this->kelasA, $this->kelasB] = Kelas::factory()->count(2)->create();
    $this->tugasA = PembagianMengajar::factory()->for($this->tahunAjaran)->for($this->kelasA)->for($this->guru)->create(['jp_per_minggu' => 2]);
    $this->tugasB = PembagianMengajar::factory()->for($this->tahunAjaran)->for($this->kelasB)->for($this->guru)->create(['jp_per_minggu' => 2]);
});

function sel(Kelas $kelas, Hari $hari, int $jp): array
{
    return ['kelas' => $kelas->id, 'hari' => $hari->value, 'jp' => $jp];
}

it('lets the vice principal for curriculum manage timetables but not student records', function () {
    actingAs(User::factory()->create()->assignRole(RoleSeeder::WK_KURIKULUM));

    foreach ([JadwalResource::getUrl(), AturJadwal::getUrl(['record' => $this->jadwal]), JamPelajaranResource::getUrl(), KalenderAkademikResource::getUrl(), PermintaanJadwalResource::getUrl()] as $url) {
        $this->get($url)->assertOk();
    }

    $this->get(SiswaResource::getUrl())->assertForbidden();
});

it('fills a period by hand, locked, and refuses to put a teacher in two classes at once', function () {
    actingAs(User::factory()->create()->assignRole('operator'));

    Livewire::test(AturJadwal::class, ['record' => $this->jadwal->id])
        ->callAction('aturSel', ['pembagian_mengajar_id' => $this->tugasA->id, 'is_terkunci' => true], sel($this->kelasA, Hari::Senin, 1))
        ->assertHasNoActionErrors()
        ->callAction('aturSel', ['pembagian_mengajar_id' => $this->tugasB->id, 'is_terkunci' => true], sel($this->kelasB, Hari::Senin, 1))
        ->assertNotified('Bentrok');

    expect($this->jadwal->isi()->sole())
        ->kelas_id->toBe($this->kelasA->id)
        ->is_terkunci->toBeTrue();
});

it('empties a period', function () {
    actingAs(User::factory()->create()->assignRole('operator'));
    IsiJadwal::factory()->for($this->jadwal)->for($this->tugasA)->create(['kelas_id' => $this->kelasA->id]);

    Livewire::test(AturJadwal::class, ['record' => $this->jadwal->id])
        ->callAction('aturSel', ['pembagian_mengajar_id' => null], sel($this->kelasA, Hari::Senin, 1));

    expect($this->jadwal->isi()->count())->toBe(0);
});

it('builds the timetable automatically and publishes it, returning the previous one to draft', function () {
    actingAs(User::factory()->create()->assignRole('operator'));
    $lama = Jadwal::factory()->for($this->tahunAjaran)->create(['semester' => $this->jadwal->semester, 'status' => StatusJadwal::Terbit]);

    Livewire::test(AturJadwal::class, ['record' => $this->jadwal->id])
        ->callAction('buatOtomatis')
        ->assertNotified('Jadwal berhasil disusun')
        ->callAction('terbitkan');

    expect($this->jadwal->refresh()->status)->toBe(StatusJadwal::Terbit)
        ->and($this->jadwal->isi()->count())->toBe(4)
        ->and($lama->refresh()->status)->toBe(StatusJadwal::Draf);
});

it('prints the timetable with its codes and signatures', function () {
    actingAs(User::factory()->create()->assignRole('operator'));
    IsiJadwal::factory()->for($this->jadwal)->for($this->tugasA)->create(['kelas_id' => $this->kelasA->id]);

    $this->get(route('jadwal.cetak', $this->jadwal))
        ->assertOk()
        ->assertSee('JADWAL PELAJARAN')
        ->assertSee('Bu Siti')
        ->assertSee('Shalat Dzuhur');

    $this->get(route('jadwal.cetak', ['jadwal' => $this->jadwal, 'format' => 'pdf']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('shows teachers their lessons of the published timetable only', function () {
    Filament::setCurrentPanel('guru');
    IsiJadwal::factory()->for($this->jadwal)->for($this->tugasA)->create(['kelas_id' => $this->kelasA->id, 'hari' => Hari::Selasa, 'jp_ke' => 3]);
    actingAs($this->guru->user);

    $this->get(JadwalSaya::getUrl())->assertOk()->assertSee('Jadwal belum terbit');
    $this->get(route('jadwal.cetak', $this->jadwal))->assertForbidden();

    $this->jadwal->terbitkan();

    $this->get(JadwalSaya::getUrl())->assertOk()->assertSee('Kelas '.$this->kelasA->nama);
    $this->get(route('jadwal.cetak', $this->jadwal))->assertOk();
    $this->get(filament()->getUrl())->assertOk();
});

it('lets teachers keep their own scheduling wishes', function () {
    Filament::setCurrentPanel('guru');
    $milikLain = PermintaanJadwal::factory()->create();
    actingAs($this->guru->user);

    Livewire::test(ManagePermintaanJadwalSaya::class)
        ->assertCanNotSeeTableRecords([$milikLain])
        ->callAction('create', [
            'jenis' => JenisPermintaanJadwal::TidakBisa->value,
            'hari' => [Hari::Jumat->value],
            'sifat' => SifatPermintaanJadwal::Wajib->value,
            'keterangan' => 'Mengajar di SMP lain',
        ])
        ->assertHasNoActionErrors();

    expect($this->guru->permintaanJadwal()->sole())
        ->hari->toBe([Hari::Jumat->value])
        ->ringkasan()->toBe("Tidak bisa mengajar pada Jum'at, seharian");
});

it('fills in the default bell schedule', function () {
    actingAs(User::factory()->create()->assignRole('operator'));
    JamPelajaran::query()->delete();

    Livewire::test(ManageJamPelajarans::class)->callAction('isiBawaan');

    expect(JamPelajaran::query()->where('jenis', 'pelajaran')->count())->toBe(48);
});

it('shows the coming agenda on the teacher dashboard', function () {
    Filament::setCurrentPanel('guru');
    KalenderAkademik::factory()->for($this->tahunAjaran)->create(['nama' => 'Penilaian Tengah Semester', 'tanggal_mulai' => today()->addDays(3), 'tanggal_selesai' => today()->addDays(8)]);

    actingAs($this->guru->user);

    Livewire::test(AgendaMadrasah::class)->assertSee('Penilaian Tengah Semester');
});

it('shows the teacher today\'s lessons', function () {
    Filament::setCurrentPanel('guru');
    $this->travelTo(now()->next('Tuesday'));
    IsiJadwal::factory()->for($this->jadwal)->for($this->tugasA)->create(['kelas_id' => $this->kelasA->id, 'hari' => Hari::Selasa, 'jp_ke' => 3]);
    $this->jadwal->update(['semester' => Semester::berdasarkanTanggal(now())]);
    $this->jadwal->terbitkan();
    actingAs($this->guru->user);

    Livewire::test(JadwalHariIni::class)->assertCanSeeTableRecords($this->jadwal->isi()->get());
});
