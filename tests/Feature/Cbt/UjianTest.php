<?php

use App\Enums\JenisSoal;
use App\Filament\Ujian\Resources\Soals\Pages\ListSoals;
use App\Filament\Ujian\Resources\Ujians\Pages\CreateUjian;
use App\Filament\Ujian\Resources\Ujians\Pages\NilaiUjian;
use App\Filament\Ujian\Resources\Ujians\UjianResource;
use App\Models\Gtk;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\PembagianMengajar;
use App\Models\Siswa;
use App\Models\Soal;
use App\Models\TahunAjaran;
use App\Models\Ujian;
use App\Services\Cbt\NaskahUjian;
use App\Services\Cbt\PenilaianUjian;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Filament::setCurrentPanel('ujian');
    $this->tahunAjaran = TahunAjaran::factory()->aktif()->create();
    $this->mapel = MataPelajaran::factory()->create(['nama' => 'IPA']);
    $this->gtk = Gtk::factory()->denganAkun()->create();
    $this->guru = $this->gtk->user;
    PembagianMengajar::factory()->for($this->tahunAjaran)->for($this->mapel)->for($this->gtk)->create();

    $this->ujian = Ujian::factory()->for($this->tahunAjaran)->for($this->mapel)->create(['user_id' => $this->guru->id, 'jumlah_paket' => 2]);
});

/**
 * One question of each kind, added to the exam in the order given (esai first, to show the paper regroups them).
 *
 * @return array<string, Soal>
 */
function soalTiapJenis(Ujian $ujian): array
{
    $buat = fn (array $isian): Soal => Soal::factory()->create(['mata_pelajaran_id' => $ujian->mata_pelajaran_id, 'tingkat' => $ujian->tingkat, ...$isian]);

    $soal = [
        'esai' => $buat(['jenis' => JenisSoal::Esai, 'pertanyaan' => '<p>Jelaskan fotosintesis.</p>', 'opsi' => null, 'pembahasan' => 'Cahaya + klorofil (2 poin)']),
        'pg' => $buat(['jenis' => JenisSoal::PilihanGanda, 'opsi' => [
            ['teks' => 'Mars', 'benar' => false], ['teks' => 'Bumi', 'benar' => true], ['teks' => 'Venus', 'benar' => false], ['teks' => 'Saturnus', 'benar' => false],
        ]]),
        'kompleks' => $buat(['jenis' => JenisSoal::PgKompleks, 'opsi' => [
            ['teks' => 'Paru-paru', 'benar' => true], ['teks' => 'Ginjal', 'benar' => false], ['teks' => 'Insang', 'benar' => true], ['teks' => 'Usus', 'benar' => false],
        ]]),
        'bs' => $buat(['jenis' => JenisSoal::BenarSalah, 'opsi' => [['teks' => 'Air mendidih pada 100 °C', 'benar' => true], ['teks' => 'Es lebih berat dari air', 'benar' => false]]]),
        'jodoh' => $buat(['jenis' => JenisSoal::Menjodohkan, 'opsi' => [
            ['teks' => 'Ibu kota Jawa Barat', 'pasangan' => 'Bandung'], ['teks' => 'Ibu kota Jawa Timur', 'pasangan' => 'Surabaya'], ['teks' => 'Ibu kota Bali', 'pasangan' => 'Denpasar'],
        ]]),
    ];

    foreach (array_values($soal) as $i => $s) {
        $ujian->butir()->create(['soal_id' => $s->id, 'urutan' => $i + 1, 'bobot' => $s->jenis === JenisSoal::Esai ? 5 : 1]);
    }

    return $soal;
}

it('groups the paper by kind, numbers it straight through and keys every packet', function () {
    soalTiapJenis($this->ujian);

    $a = NaskahUjian::susun($this->ujian, 'A');

    expect(array_map(fn (array $b): JenisSoal => $b['jenis'], $a))->toBe(NaskahUjian::URUTAN_JENIS)
        ->and(array_map(fn (array $b): int => $b['butir'][0]['nomor'], $a))->toBe([1, 2, 3, 4, 5])
        ->and($a[0]['butir'][0]['kunci'])->toBe('B')
        ->and($a[1]['butir'][0]['kunci'])->toBe('A, C')
        ->and($a[2]['butir'][0]['kunci'])->toBe('1. B, 2. S')
        ->and($a[4]['butir'][0]['kunci'])->toBe('Cahaya + klorofil (2 poin)');

    // Whatever order a packet prints, each key letter points at the right answer.
    foreach (['A', 'B'] as $paket) {
        $naskah = NaskahUjian::susun($this->ujian, $paket);
        expect($naskah)->toEqual(NaskahUjian::susun($this->ujian, $paket));

        $pg = $naskah[0]['butir'][0];
        expect(collect($pg['opsi'])->firstWhere('huruf', $pg['kunci'])['teks'])->toBe('Bumi');

        $kompleks = $naskah[1]['butir'][0];
        expect(collect($kompleks['opsi'])->whereIn('huruf', explode(', ', $kompleks['kunci']))->pluck('teks')->sort()->values()->all())->toBe(['Insang', 'Paru-paru']);

        $jodoh = $naskah[3]['butir'][0];
        $kanan = collect($jodoh['kanan'])->pluck('teks', 'huruf');
        expect(collect(explode(', ', $jodoh['kunci']))->map(fn (string $k): string => $kanan[explode('-', $k)[1]])->all())->toBe(['Bandung', 'Surabaya', 'Denpasar']);
    }
});

it('lets a teacher put an exam together from ready questions of the subject', function () {
    $siap = Soal::factory()->for($this->mapel)->create();
    $draf = Soal::factory()->for($this->mapel)->create(['is_siap' => false]);
    actingAs($this->guru);

    Livewire::test(CreateUjian::class)
        ->fillForm([
            'nama' => 'Penilaian Akhir Semester',
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'mata_pelajaran_id' => $this->mapel->id,
            'tingkat' => 7,
            'semester' => 1,
            'durasi_menit' => 90,
            'jumlah_paket' => 1,
            'butir' => [['soal_id' => $siap->id, 'bobot' => 2]],
        ])
        ->assertFormFieldExists('butir')
        ->call('create')
        ->assertHasNoFormErrors();

    $ujian = Ujian::query()->latest('id')->first();
    expect($ujian->user_id)->toBe($this->guru->id)
        ->and($ujian->butir->pluck('soal_id')->all())->toBe([$siap->id])
        ->and($ujian->butir->first()->bobot)->toBe(2);

    Livewire::test(CreateUjian::class)
        ->fillForm(['nama' => 'X', 'tahun_ajaran_id' => $this->tahunAjaran->id, 'mata_pelajaran_id' => $this->mapel->id, 'tingkat' => 7, 'semester' => 1, 'durasi_menit' => 90, 'butir' => [['soal_id' => $draf->id, 'bobot' => 1]]])
        ->call('create')
        ->assertHasFormErrors();
});

it('adds ticked questions from the bank, skipping drafts and questions already in', function () {
    $sudahAda = Soal::factory()->for($this->mapel)->create();
    $this->ujian->butir()->create(['soal_id' => $sudahAda->id, 'urutan' => 1]);
    $baru = Soal::factory()->for($this->mapel)->create();
    $draf = Soal::factory()->for($this->mapel)->create(['is_siap' => false]);
    actingAs($this->guru);

    Livewire::test(ListSoals::class, ['mapel' => $this->mapel->id])
        ->selectTableRecords([$sudahAda, $baru, $draf])
        ->callAction(TestAction::make('masukkanKeUjian')->table()->bulk(), ['ujian_id' => $this->ujian->id])
        ->assertNotified();

    expect($this->ujian->butir()->pluck('soal_id')->all())->toBe([$sudahAda->id, $baru->id]);
});

it('prints the paper, the key and the blueprint for those who may see the exam', function () {
    soalTiapJenis($this->ujian);
    actingAs($this->guru);

    $this->get(route('ujian.cetak', [$this->ujian, 'naskah', 'B']))->assertOk()
        ->assertSee('Naskah Soal')->assertSee('Jelaskan fotosintesis.')->assertSee('Ibu kota Bali');
    $this->get(route('ujian.cetak', [$this->ujian, 'kunci']))->assertOk()->assertSee('Paket A')->assertSee('Paket B')->assertSee('Skor maksimal');
    $this->get(route('ujian.cetak', [$this->ujian, 'kisi-kisi']))->assertOk()->assertSee('Kisi-kisi Soal');
    $this->get(route('ujian.cetak', [$this->ujian, 'naskah', 'C']))->assertNotFound();

    $guruLain = Gtk::factory()->denganAkun()->create()->user;
    actingAs($guruLain)->get(route('ujian.cetak', [$this->ujian, 'naskah']))->assertForbidden();
});

it('keeps a question that an exam uses', function () {
    $soal = Soal::factory()->for($this->mapel)->create();
    $this->ujian->butir()->create(['soal_id' => $soal->id]);

    expect($soal->delete())->toBeFalse()
        ->and($soal->keteranganPemakaian())->toContain('1 ujian');
});

it('scores answers proportionally for every kind of question', function () {
    $soal = soalTiapJenis($this->ujian);
    $butir = collect(NaskahUjian::susun($this->ujian, 'A'))->pluck('butir')->flatten(1)->keyBy(fn (array $b): int => $b['soal']->id);
    $skor = fn (string $jenis, mixed $jawaban): float => PenilaianUjian::skor($butir[$soal[$jenis]->id], $jawaban);

    expect($skor('pg', 'b'))->toBe(1.0)
        ->and($skor('pg', 'A'))->toBe(0.0)
        ->and($skor('kompleks', 'a, c'))->toBe(1.0)
        ->and($skor('kompleks', 'A'))->toBe(0.5)
        ->and($skor('kompleks', 'ABC'))->toBe(0.5)
        ->and($skor('kompleks', 'ABCD'))->toBe(0.0)
        ->and($skor('bs', 'BB'))->toBe(0.5)
        ->and($skor('bs', 'BS'))->toBe(1.0)
        ->and($skor('jodoh', $butir[$soal['jodoh']->id]['kunciHuruf']))->toBe(1.0)
        ->and($skor('esai', 3))->toBe(3.0)
        ->and($skor('esai', 9))->toBe(5.0)
        ->and($skor('pg', null))->toBe(0.0);
});

it('lists the grade\'s students, takes typed answers per packet and analyses the questions', function () {
    $soal = soalTiapJenis($this->ujian);
    $kelas = Kelas::factory()->create(['tingkat' => 7, 'nama' => '7-A', 'is_aktif' => true]);
    Kelas::factory()->create(['tingkat' => 8, 'is_aktif' => true])->siswa()->save(Siswa::factory()->make());
    collect(['Ahmad', 'Budi', 'Citra'])->each(fn (string $nama): Siswa => Siswa::factory()->create(['nama_lengkap' => $nama, 'kelas_id' => $kelas->id]));
    actingAs($this->guru);

    $halaman = Livewire::test(NilaiUjian::class, ['record' => $this->ujian->getRouteKey()]);

    $peserta = $this->ujian->peserta()->with('siswa')->get()->keyBy('siswa.nama_lengkap');
    expect($peserta)->toHaveCount(3)
        ->and($peserta->map->paket->all())->toBe(['Ahmad' => 'A', 'Budi' => 'B', 'Citra' => 'A']);

    // Budi sat packet B, where the PG choices are shuffled: his letter must still be read against packet B's key.
    $naskahB = collect(NaskahUjian::susun($this->ujian, 'B'))->pluck('butir')->flatten(1)->keyBy(fn (array $b): int => $b['soal']->id);
    $semuaBenar = fn (Collection $naskah): array => $naskah->mapWithKeys(fn (array $b, int $id): array => [$id => $b['soal']->jenis === JenisSoal::Esai ? 5 : $b['kunciHuruf']])->all();

    $halaman
        ->callAction(TestAction::make('isiJawaban')->table($peserta['Budi']), ['jawaban' => $semuaBenar($naskahB)])
        ->assertHasNoFormErrors()
        ->callAction(TestAction::make('isiJawaban')->table($peserta['Ahmad']), ['jawaban' => [$soal['pg']->id => 'A', $soal['esai']->id => 1]])
        ->assertHasNoFormErrors()
        ->callAction(TestAction::make('isiJawaban')->table($peserta['Citra']), ['jawaban' => [$soal['pg']->id => 'Z']])
        ->assertHasFormErrors();

    expect($peserta['Budi']->refresh()->nilai)->toBe('100.00')
        ->and($peserta['Ahmad']->refresh()->nilai)->toBe('11.11') // 1 of 9 points: the essay
        ->and($peserta['Citra']->refresh()->nilai)->toBeNull();

    $analisis = PenilaianUjian::analisis($this->ujian);
    $pg = collect($analisis['butir'])->firstWhere('soal.id', $soal['pg']->id);
    expect($analisis['peserta'])->toBe(2)
        ->and($pg['kesukaran'])->toBe(0.5)
        ->and($pg['dayaBeda'])->toBe(1.0)
        ->and(collect($pg['pengecoh'])->pluck('persen', 'huruf')->all())->toBe(['A' => 0.5, 'B' => 0.5, 'C' => 0.0, 'D' => 0.0]);

    $this->get(UjianResource::getUrl('analisis', ['record' => $this->ujian]))->assertOk()->assertSee('Pakai lagi', false);
    Livewire::test(NilaiUjian::class, ['record' => $this->ujian->getRouteKey()])->callAction('unduh')->assertFileDownloaded();
});

it('shows the scores to the subject\'s other teachers but keeps answer entry to the author', function () {
    $siswa = Siswa::factory()->create();
    $peserta = $this->ujian->peserta()->create(['siswa_id' => $siswa->id, 'paket' => 'A']);
    $rekan = Gtk::factory()->denganAkun()->create();
    PembagianMengajar::factory()->for($this->tahunAjaran)->for($this->mapel)->for($rekan)->create();
    actingAs($rekan->user);

    Livewire::test(NilaiUjian::class, ['record' => $this->ujian->getRouteKey()])
        ->assertCanSeeTableRecords([$peserta])
        ->assertActionHidden(TestAction::make('isiJawaban')->table($peserta));

    actingAs(Gtk::factory()->denganAkun()->create()->user)
        ->get(UjianResource::getUrl('nilai', ['record' => $this->ujian]))
        ->assertNotFound();
});
