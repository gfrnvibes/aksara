<?php

use App\Enums\KeputusanKenaikan;
use App\Enums\Semester;
use App\Filament\Resources\Rapors\Pages\CreateRapor;
use App\Models\Ekstrakurikuler;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Notification;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    actingAs(userWithPermissions(['ViewAny:Rapor', 'Create:Rapor']));

    TahunAjaran::factory()->aktif()->create();

    $this->siswa = Siswa::factory()->create(['nis' => '250001', 'nama_lengkap' => 'Ahmad Fauzi Ramadhan', 'nama_panggilan' => 'Fauzi']);
    $this->matematika = MataPelajaran::factory()->create(['nama' => 'Matematika', 'kkm' => 75]);
    $this->fikih = MataPelajaran::factory()->create(['nama' => 'Fikih', 'kkm' => 75]);
    $this->pramuka = Ekstrakurikuler::factory()->create(['nama' => 'Pramuka']);
});

function formRaporTerisi(Siswa $siswa, MataPelajaran $matematika, MataPelajaran $fikih, Ekstrakurikuler $pramuka): Testable
{
    return Livewire::test(CreateRapor::class)->fillForm([
        'siswa_id' => $siswa->id,
        'nilaiRapor' => [
            ['mata_pelajaran_id' => $matematika->id, 'kkm' => 75, 'nilai_pengetahuan' => 95, 'nilai_keterampilan' => 92, 'sikap' => 'SB'],
            ['mata_pelajaran_id' => $fikih->id, 'kkm' => 75, 'nilai_pengetahuan' => 68, 'nilai_keterampilan' => 70, 'sikap' => 'B'],
        ],
        'nilaiEkstrakurikuler' => [['ekstrakurikuler_id' => $pramuka->id, 'nilai' => 'A']],
        'nilai_akhlak' => 'A',
        'nilai_kepribadian' => 'A',
        'sakit' => 1,
        'izin' => 0,
        'tanpa_keterangan' => 3,
    ]);
}

it('drafts the note from the rules when Gemini is not configured', function () {
    config(['services.gemini.key' => null]);
    Http::fake();

    $catatan = formRaporTerisi($this->siswa, $this->matematika, $this->fikih, $this->pramuka)
        ->callAction(TestAction::make('buatCatatan')->schemaComponent('catatan'))
        ->assertNotified('Catatan dibuat dari aturan')
        ->get('data.catatan');

    expect($catatan)
        ->toStartWith('Ananda Fauzi ')
        ->toContain('Kemampuan terbaik terlihat pada Matematika.')
        ->toContain('Nilai Fikih masih di bawah KKM')
        ->toContain('berprestasi dalam kegiatan Pramuka')
        ->toContain('3 hari tanpa keterangan');

    Http::assertNothingSent();
});

it('ends an even-semester note with the promotion to the next grade', function () {
    config(['services.gemini.key' => null]);
    $kelas = Kelas::factory()->create(['tingkat' => 7, 'nama' => 'VII-A']);

    $catatan = formRaporTerisi($this->siswa, $this->matematika, $this->fikih, $this->pramuka)
        ->fillForm(['semester' => Semester::Genap->value, 'kelas_id' => $kelas->id, 'keputusan' => KeputusanKenaikan::Naik->value])
        ->callAction(TestAction::make('buatCatatan')->schemaComponent('catatan'))
        ->get('data.catatan');

    expect($catatan)->toEndWith('Selamat, ananda dinyatakan naik ke kelas VIII; tetap semangat belajar di kelas yang baru.');
});

it('ignores a decision left over from switching to the odd semester', function () {
    config(['services.gemini.key' => null]);

    $catatan = formRaporTerisi($this->siswa, $this->matematika, $this->fikih, $this->pramuka)
        ->fillForm(['semester' => Semester::Genap->value, 'keputusan' => KeputusanKenaikan::Lulus->value])
        ->fillForm(['semester' => Semester::Ganjil->value])
        ->callAction(TestAction::make('buatCatatan')->schemaComponent('catatan'))
        ->get('data.catatan');

    expect($catatan)->not->toContain('kelulusan');
});

it('sends the decision to Gemini on an even-semester report card', function () {
    config(['services.gemini.key' => 'kunci-uji', 'services.gemini.model' => 'model-uji']);
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => 'Ananda dinyatakan lulus.']]]]],
    ])]);

    formRaporTerisi($this->siswa, $this->matematika, $this->fikih, $this->pramuka)
        ->fillForm(['semester' => Semester::Genap->value, 'keputusan' => KeputusanKenaikan::Lulus->value])
        ->callAction(TestAction::make('buatCatatan')->schemaComponent('catatan'));

    Http::assertSent(fn (Request $request): bool => $request['contents'][0]['parts'][0]['text'] !== null
        && json_decode($request['contents'][0]['parts'][0]['text'], true)['keputusan_akhir_tahun'] === ['keputusan' => 'Lulus', 'naik_ke_kelas' => null]);
});

it('drafts the note with Gemini without sending who the student is', function () {
    config(['services.gemini.key' => 'kunci-uji', 'services.gemini.model' => 'gemini-3.8-flash']);
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => "Ananda menunjukkan semangat belajar yang baik.\nTerus tingkatkan Fikih."]]], 'finishReason' => 'STOP']],
        ]),
    ]);

    formRaporTerisi($this->siswa, $this->matematika, $this->fikih, $this->pramuka)
        ->callAction(TestAction::make('buatCatatan')->schemaComponent('catatan'))
        ->assertNotified('Catatan dibuat dengan Gemini')
        ->assertSchemaStateSet(['catatan' => 'Ananda Fauzi menunjukkan semangat belajar yang baik. Terus tingkatkan Fikih.']);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent'
        && $request->hasHeader('x-goog-api-key', 'kunci-uji')
        && str_contains($request->body(), 'Matematika')
        && ! str_contains($request->body(), 'Fauzi')
        && ! str_contains($request->body(), '250001'));
});

it('falls back to the rules when Gemini is unavailable', function () {
    config(['services.gemini.key' => 'kunci-uji']);
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'Quota exceeded']], 429)]);

    $catatan = formRaporTerisi($this->siswa, $this->matematika, $this->fikih, $this->pramuka)
        ->callAction(TestAction::make('buatCatatan')->schemaComponent('catatan'))
        ->assertNotified('Catatan dibuat dari aturan')
        ->get('data.catatan');

    expect($catatan)->toContain('Nilai Fikih masih di bawah KKM');
});

it('moves on to the next model while the first one is busy', function () {
    config(['services.gemini.key' => 'kunci-uji', 'services.gemini.model' => 'model-sibuk, model-cadangan']);
    Http::fake([
        '*/models/model-sibuk:generateContent' => Http::response(['error' => ['message' => 'high demand']], 503),
        '*/models/model-cadangan:generateContent' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Ananda rajin belajar.']]]]],
        ]),
    ]);

    formRaporTerisi($this->siswa, $this->matematika, $this->fikih, $this->pramuka)
        ->callAction(TestAction::make('buatCatatan')->schemaComponent('catatan'))
        ->assertNotified('Catatan dibuat dengan Gemini')
        ->assertSchemaStateSet(['catatan' => 'Ananda Fauzi rajin belajar.']);
});

it('tells the admin why Gemini was not used', function (int $status, string $alasan, int $permintaan) {
    config(['services.gemini.key' => 'kunci-uji', 'services.gemini.model' => 'model-a,model-b']);
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => '-']], $status)]);

    formRaporTerisi($this->siswa, $this->matematika, $this->fikih, $this->pramuka)
        ->callAction(TestAction::make('buatCatatan')->schemaComponent('catatan'))
        ->assertNotified(
            Notification::make()
                ->title('Catatan dibuat dari aturan')
                ->body("Gemini tidak dapat dipakai ({$alasan}), jadi catatan disusun dari aturan. Coba lagi beberapa saat lagi, atau periksa dan sesuaikan catatan ini sebelum menyimpan.")
                ->color('warning'),
        );

    Http::assertSentCount($permintaan);
})->with([
    'server sibuk, kedua model dicoba' => [503, 'server Gemini sedang sibuk', 2],
    'kuota habis, kedua model dicoba' => [429, 'kuota gratis Gemini sedang habis', 2],
    'key ditolak, langsung berhenti' => [403, 'API key atau nama model Gemini ditolak; periksa GEMINI_API_KEY dan GEMINI_MODEL', 1],
]);

it('falls back to the rules when Gemini blocks the request', function () {
    config(['services.gemini.key' => 'kunci-uji']);
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['promptFeedback' => ['blockReason' => 'SAFETY']])]);

    formRaporTerisi($this->siswa, $this->matematika, $this->fikih, $this->pramuka)
        ->callAction(TestAction::make('buatCatatan')->schemaComponent('catatan'))
        ->assertNotified('Catatan dibuat dari aturan');
});

it('asks before replacing a note that was already written', function () {
    config(['services.gemini.key' => null]);

    $form = formRaporTerisi($this->siswa, $this->matematika, $this->fikih, $this->pramuka)
        ->fillForm(['catatan' => 'Catatan tulisan wali kelas.'])
        ->mountAction(TestAction::make('buatCatatan')->schemaComponent('catatan'));

    expect($form->get('data.catatan'))->toBe('Catatan tulisan wali kelas.');

    $form->callMountedAction();

    expect($form->get('data.catatan'))->toStartWith('Ananda Fauzi ');
});
