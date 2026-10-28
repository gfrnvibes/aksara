<?php

use App\Enums\Semester;
use App\Filament\Pages\InputNilaiKelas;
use App\Filament\Resources\Rapors\Pages\CreateRapor;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->tahunAjaran = TahunAjaran::factory()->merdeka()->aktif()->create();
    $this->kelas = Kelas::factory()->create(['tingkat' => 8, 'nama' => 'VIII-A']);
    $this->matematika = MataPelajaran::factory()->create(['nama' => 'Matematika', 'kkm' => 75]);
    $this->ipa = MataPelajaran::factory()->create(['nama' => 'IPA', 'kkm' => 75]);
});

/**
 * Answer every Gemini request with a description per requested key, so tests can see what was asked.
 */
function geminiMenjawabCapaian(): void
{
    config(['services.gemini.key' => 'kunci-uji', 'services.gemini.model' => 'gemini-uji']);

    Http::fake(function (Request $request) {
        $permintaan = json_decode($request['contents'][0]['parts'][0]['text'], true);

        return Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode(array_map(
            fn (array $item): array => ['kunci' => $item['kunci'], 'capaian' => "{$item['awalan']} {$item['mata_pelajaran']} versi AI."],
            $permintaan['item'],
        ))]]]]]]);
    });
}

function inputNilaiMatematika(mixed $test): Testable
{
    actingAs(userWithPermissions([InputNilaiKelas::IZIN]));

    return Livewire::test(InputNilaiKelas::class)->fillForm([
        'tahun_ajaran_id' => $test->tahunAjaran->id,
        'semester' => Semester::Ganjil->value,
        'kelas_id' => $test->kelas->id,
        'mata_pelajaran_id' => $test->matematika->id,
    ]);
}

it('drafts each subject description from the final score and KKTP without Gemini', function () {
    config(['services.gemini.key' => null]);
    Http::fake();
    actingAs(userWithPermissions(['ViewAny:Rapor', 'Create:Rapor']));

    $form = Livewire::test(CreateRapor::class)->fillForm([
        'tahun_ajaran_id' => $this->tahunAjaran->id,
        'nilaiRapor' => [
            'mtk' => ['mata_pelajaran_id' => $this->matematika->id, 'kkm' => 75, 'nilai_akhir' => 88],
            'ipa' => ['mata_pelajaran_id' => $this->ipa->id, 'kkm' => 75, 'nilai_akhir' => 60],
        ],
    ]);

    $form
        ->callAction(TestAction::make('buatCapaian')->schemaComponent('nilaiMataPelajaran'))
        ->assertNotified('Capaian 2 baris dibuat dari aturan');

    expect($form->get('data.nilaiRapor.mtk.capaian_kompetensi'))->toBe('Menunjukkan penguasaan yang baik dalam memahami dan menerapkan materi Matematika.')
        ->and($form->get('data.nilaiRapor.ipa.capaian_kompetensi'))->toBe('Perlu bimbingan dalam memahami dan menerapkan materi IPA.');

    Http::assertNothingSent();
});

it('asks Gemini once per mastery level for a whole class and never sends who the students are', function () {
    geminiMenjawabCapaian();
    $siswa = collect([93, 95, 70])->map(fn (int $nilai, int $urutan) => [
        Siswa::factory()->for($this->kelas)->create(['nama_lengkap' => "Siswa Rahasia {$urutan}", 'nis' => "99000{$urutan}"]),
        $nilai,
    ]);

    $halaman = inputNilaiMatematika($this);

    foreach ($siswa as [$murid, $nilai]) {
        $kunci = collect($halaman->get('data.nilai'))->search(fn (array $baris): bool => $baris['siswa_id'] === $murid->id);
        $halaman->set("data.nilai.{$kunci}.nilai_akhir", $nilai);
    }

    $halaman
        ->callAction(TestAction::make('buatCapaian')->schemaComponent('daftarNilai'), ['materi' => 'operasi bilangan bulat'])
        ->assertNotified('Capaian 3 baris dibuat dengan Gemini');

    expect(collect($halaman->get('data.nilai'))->pluck('capaian_kompetensi')->sort()->values()->all())->toBe([
        'Menunjukkan penguasaan yang sangat baik dalam Matematika versi AI.',
        'Menunjukkan penguasaan yang sangat baik dalam Matematika versi AI.',
        'Perlu bimbingan dalam Matematika versi AI.',
    ]);

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request): bool {
        $isi = $request['contents'][0]['parts'][0]['text'];
        $permintaan = json_decode($isi, true);

        return count($permintaan['item']) === 2
            && $permintaan['item'][0]['materi'] === 'operasi bilangan bulat'
            && $permintaan['konteks'] === ['kelas' => 'VIII', 'fase' => 'D', 'semester' => 'Semester 1 (Ganjil)']
            && ! str_contains($isi, 'Siswa Rahasia')
            && ! str_contains($isi, '99000')
            && array_keys($permintaan['item'][0]) === ['kunci', 'mata_pelajaran', 'awalan', 'materi'];
    });
});

it('keeps descriptions already written unless asked to replace them', function (bool $timpa, string $harapan) {
    config(['services.gemini.key' => null]);
    $murid = Siswa::factory()->for($this->kelas)->create();

    $halaman = inputNilaiMatematika($this);
    $kunci = array_key_first($halaman->get('data.nilai'));

    $halaman
        ->set("data.nilai.{$kunci}.nilai_akhir", 80)
        ->set("data.nilai.{$kunci}.capaian_kompetensi", 'Ditulis guru.')
        ->callAction(TestAction::make('buatCapaian')->schemaComponent('daftarNilai'), ['timpa' => $timpa]);

    expect($halaman->get("data.nilai.{$kunci}.capaian_kompetensi"))->toBe($harapan);
})->with([
    'dibiarkan' => [false, 'Ditulis guru.'],
    'diganti' => [true, 'Menunjukkan penguasaan yang cukup baik dalam memahami dan menerapkan materi Matematika.'],
]);

it('falls back to the rules and says why when Gemini is unavailable', function () {
    config(['services.gemini.key' => 'kunci-uji', 'services.gemini.model' => 'gemini-uji']);
    Http::fake(['*' => Http::response([], 429)]);
    Siswa::factory()->for($this->kelas)->create();

    $halaman = inputNilaiMatematika($this);
    $kunci = array_key_first($halaman->get('data.nilai'));

    $halaman
        ->set("data.nilai.{$kunci}.nilai_akhir", 98)
        ->callAction(TestAction::make('buatCapaian')->schemaComponent('daftarNilai'))
        ->assertNotified('Capaian 1 baris dibuat dari aturan');

    expect($halaman->get("data.nilai.{$kunci}.capaian_kompetensi"))->toBe('Menunjukkan penguasaan yang sangat baik dalam memahami dan menerapkan materi Matematika.');
});

it('offers the description draft only for Kurikulum Merdeka years', function () {
    actingAs(userWithPermissions(['ViewAny:Rapor', 'Create:Rapor']));

    Livewire::test(CreateRapor::class)
        ->fillForm(['tahun_ajaran_id' => TahunAjaran::factory()->create()->id])
        ->assertActionDoesNotExist(TestAction::make('buatCapaian')->schemaComponent('nilaiMataPelajaran'))
        ->fillForm(['tahun_ajaran_id' => $this->tahunAjaran->id])
        ->assertActionVisible(TestAction::make('buatCapaian')->schemaComponent('nilaiMataPelajaran'));
});
