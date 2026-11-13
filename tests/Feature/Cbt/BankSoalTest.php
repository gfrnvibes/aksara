<?php

use App\Enums\JenisSoal;
use App\Filament\Ujian\Resources\Soals\Pages\BuatSoalAi;
use App\Filament\Ujian\Resources\Soals\Pages\CreateSoal;
use App\Filament\Ujian\Resources\Soals\Pages\ListSoals;
use App\Filament\Ujian\Resources\Soals\SoalResource;
use App\Models\Gtk;
use App\Models\MataPelajaran;
use App\Models\PembagianMengajar;
use App\Models\Soal;
use App\Models\TahunAjaran;
use App\Models\Ujian;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Filament::setCurrentPanel('ujian');
    $this->tahunAjaran = TahunAjaran::factory()->aktif()->create();
    $this->mapel = MataPelajaran::factory()->create(['nama' => 'IPA']);
    $this->mapelLain = MataPelajaran::factory()->create(['nama' => 'IPS']);

    $this->gtk = Gtk::factory()->denganAkun()->create();
    $this->guru = $this->gtk->user;
    PembagianMengajar::factory()->for($this->tahunAjaran)->for($this->mapel)->for($this->gtk)->create();

    $this->rekan = Gtk::factory()->denganAkun()->create()->user;
});

it('checks the answer key against the kind of question', function (JenisSoal $jenis, array $benar, ?string $pesan) {
    $opsi = array_map(fn (bool $b): array => ['teks' => 'x', 'benar' => $b], $benar);

    expect(Soal::kesalahanKunci($jenis, $opsi))->toBe($pesan);
})->with([
    'PG satu benar' => [JenisSoal::PilihanGanda, [true, false, false, false], null],
    'PG dua benar' => [JenisSoal::PilihanGanda, [true, true, false, false], 'Tandai tepat satu pilihan sebagai jawaban benar.'],
    'PG enam pilihan' => [JenisSoal::PilihanGanda, [true, false, false, false, false, false], 'Pilihan Ganda memerlukan 2 sampai 5 Pilihan Jawaban.'],
    'kompleks semua benar' => [JenisSoal::PgKompleks, [true, true, true, true], 'Tandai minimal satu pilihan benar dan sisakan minimal satu pilihan salah.'],
    'kompleks dua benar' => [JenisSoal::PgKompleks, [true, true, false, false], null],
    'benar/salah' => [JenisSoal::BenarSalah, [false, false, true], null],
    'esai' => [JenisSoal::Esai, [], null],
]);

it('shares a subject\'s questions with its teachers but lets only the author change them', function () {

    $milikSendiri = Soal::factory()->for($this->mapel)->create(['user_id' => $this->guru->id]);
    $milikRekan = Soal::factory()->for($this->mapel)->create(['user_id' => $this->rekan->id]);
    $mapelLain = Soal::factory()->for($this->mapelLain)->create(['user_id' => $this->rekan->id]);

    actingAs($this->guru);

    Livewire::test(ListSoals::class, ['mapel' => $this->mapel->id])
        ->assertCanSeeTableRecords([$milikSendiri, $milikRekan])
        ->assertCanNotSeeTableRecords([$mapelLain])
        ->assertActionVisible(TestAction::make('edit')->table($milikSendiri))
        ->assertActionHidden(TestAction::make('edit')->table($milikRekan))
        ->assertActionHidden(TestAction::make('delete')->table($milikRekan));

    expect(SoalResource::canEdit($milikRekan))->toBeFalse();
    $this->get(SoalResource::getUrl('edit', ['record' => $milikRekan]))->assertForbidden();
});

it('lets a teacher write a question for their own subject only', function () {
    actingAs($this->guru);

    $isian = [
        'mata_pelajaran_id' => $this->mapel->id,
        'tingkat' => 7,
        'jenis' => JenisSoal::PilihanGanda->value,
        'materi' => 'Sel',
        'pertanyaan' => '<p>Bagian sel yang mengatur seluruh kegiatan sel adalah ...</p>',
        'opsi' => [
            ['teks' => 'Inti sel', 'benar' => true],
            ['teks' => 'Membran sel', 'benar' => false],
            ['teks' => 'Sitoplasma', 'benar' => false],
        ],
        'is_siap' => true,
    ];

    Livewire::test(CreateSoal::class)
        ->fillForm([...$isian, 'opsi' => [['teks' => 'Inti sel', 'benar' => true], ['teks' => 'Membran sel', 'benar' => true]]])
        ->call('create')
        ->assertHasFormErrors(['opsi']);

    Livewire::test(CreateSoal::class)
        ->fillForm([...$isian, 'mata_pelajaran_id' => $this->mapelLain->id])
        ->call('create')
        ->assertHasFormErrors(['mata_pelajaran_id']);

    Livewire::test(CreateSoal::class)->fillForm($isian)->call('create')->assertHasNoFormErrors();

    $soal = Soal::query()->sole();
    expect($soal->user_id)->toBe($this->guru->id)
        ->and($soal->opsi)->toHaveCount(3)
        ->and($soal->opsi[0]['benar'])->toBeTrue();
});

it('drafts a blueprint table row by row in the background and rings the bell when done', function () {
    actingAs($this->guru);
    config(['services.gemini.key' => 'kunci-uji', 'services.gemini.model' => 'gemini-uji']);

    // Row 1: one good question and one with a broken key. Row 2: Gemini rejects the request outright.
    Http::fakeSequence()
        ->push(['candidates' => [['content' => ['parts' => [['text' => json_encode([
            ['pertanyaan' => 'Organ pencernaan tempat penyerapan sari makanan adalah ...', 'level_kognitif' => 'c1', 'pembahasan' => 'Usus halus menyerap sari makanan.', 'opsi' => [
                ['teks' => 'Usus halus', 'benar' => true], ['teks' => 'Lambung', 'benar' => false], ['teks' => 'Kerongkongan', 'benar' => false], ['teks' => 'Usus besar', 'benar' => false],
            ]],
            ['pertanyaan' => 'Soal rusak', 'opsi' => [['teks' => 'A', 'benar' => true], ['teks' => 'B', 'benar' => true]]],
        ])]]]]]])
        ->push([], 400);

    Livewire::test(BuatSoalAi::class, ['mapel' => $this->mapel->id])
        ->fillForm(['tingkat' => 8, 'baris' => [
            ['materi' => 'Sistem pencernaan', 'jenis' => JenisSoal::PilihanGanda->value, 'jumlah' => 2],
            ['materi' => 'Sistem pernapasan', 'jenis' => JenisSoal::Esai->value, 'jumlah' => 1],
        ]])
        ->call('kirim')
        ->assertHasNoFormErrors()
        ->assertRedirect(SoalResource::urlKelompok($this->mapel->id, 8));

    $soal = Soal::query()->sole();
    expect($soal->is_siap)->toBeFalse()
        ->and($soal->user_id)->toBe($this->guru->id)
        ->and($soal->tingkat)->toBe(8)
        ->and($soal->level_kognitif?->value)->toBe('C1')
        ->and($soal->pertanyaan)->toBe('<p>Organ pencernaan tempat penyerapan sari makanan adalah ...</p>');

    Http::assertSent(fn ($request): bool => str_contains($request->body(), 'Sistem pencernaan') && str_contains($request->body(), 'IPA'));

    $notifikasi = $this->guru->notifications()->sole()->data;
    expect($notifikasi['title'])->toBe('1 draf soal dibuat untuk IPA · Kelas VIII')
        ->and($notifikasi['body'])->toContain('Sistem pernapasan');
});

it('limits one request to ten rows and fifty questions, and only for the teacher\'s own subjects', function () {
    actingAs($this->guru);
    config(['services.gemini.key' => 'kunci-uji']);

    Livewire::test(BuatSoalAi::class, ['mapel' => $this->mapel->id])
        ->fillForm(['tingkat' => 7, 'baris' => array_fill(0, 6, ['materi' => 'Sel', 'jenis' => JenisSoal::PilihanGanda->value, 'jumlah' => 10])])
        ->call('kirim')
        ->assertHasFormErrors(['baris']);

    $this->get(SoalResource::getUrl('ai', ['mapel' => $this->mapelLain->id]))->assertForbidden();
});

it('marks ticked drafts ready, skipping questions the teacher did not write', function () {
    $milikSendiri = Soal::factory()->for($this->mapel)->create(['user_id' => $this->guru->id, 'is_siap' => false]);
    $milikRekan = Soal::factory()->for($this->mapel)->create(['user_id' => $this->rekan->id, 'is_siap' => false]);
    actingAs($this->guru);

    Livewire::test(ListSoals::class, ['mapel' => $this->mapel->id])
        ->selectTableRecords([$milikSendiri, $milikRekan])
        ->callAction(TestAction::make('tandaiSiap')->table()->bulk())
        ->assertNotified();

    expect($milikSendiri->refresh()->is_siap)->toBeTrue()
        ->and($milikRekan->refresh()->is_siap)->toBeFalse();
});

it('shows a card for each subject and grade the teacher teaches, with its question count', function () {
    [$dipakai] = Soal::factory()->for($this->mapel)->count(2)->create();
    Soal::factory()->for($this->mapel)->create(['is_siap' => false]);
    Soal::factory()->for($this->mapelLain)->create();
    Ujian::factory()->for($this->mapel)->create()->butir()->create(['soal_id' => $dipakai->id]);
    actingAs($this->guru);

    $this->get(SoalResource::getUrl())
        ->assertOk()
        ->assertSeeTextInOrder(['IPA', '3', 'VII 3', '2 siap', '1 draf', '1 di ujian'])
        ->assertSee(SoalResource::urlKelompok($this->mapel->id), false)
        ->assertDontSee('IPS');

    Livewire::test(ListSoals::class, ['mapel' => $this->mapel->id])
        ->assertSee('Kelas VII')
        ->set('activeTab', 'kelas-7')
        ->assertCanSeeTableRecords(Soal::query()->where('mata_pelajaran_id', $this->mapel->id)->get());
    $this->get(SoalResource::urlKelompok($this->mapel->id, 7))->assertOk()->assertSee('IPA');
    $this->get(SoalResource::getUrl('create', ['mapel' => $this->mapel->id, 'tingkat' => 7]))->assertOk();
    Livewire::withQueryParams(['mapel' => $this->mapel->id, 'tingkat' => 7])
        ->test(CreateSoal::class)
        ->assertSchemaStateSet(['mata_pelajaran_id' => $this->mapel->id, 'tingkat' => 7]);
});

it('removes question images from storage when they are replaced or the question is deleted', function () {
    Storage::fake(Soal::disk());
    Storage::disk(Soal::disk())->put('soal/a.png', 'a');
    Storage::disk(Soal::disk())->put('soal/b.png', 'b');

    $soal = Soal::factory()->create(['gambar' => 'soal/a.png', 'opsi' => [
        ['teks' => 'x', 'gambar' => 'soal/b.png', 'benar' => true], ['teks' => 'y', 'benar' => false],
    ]]);

    $soal->update(['gambar' => null]);
    Storage::disk(Soal::disk())->assertMissing('soal/a.png')->assertExists('soal/b.png');

    $soal->delete();
    Storage::disk(Soal::disk())->assertMissing('soal/b.png');
});

it('opens the exam panel and the whole bank to curriculum staff without a GTK record', function () {
    $soal = Soal::factory()->for($this->mapelLain)->create(['user_id' => $this->rekan->id]);

    actingAs(userWithPermissions(['ViewAny:Soal', 'Update:Soal']));

    $this->get(filament()->getUrl())->assertRedirect(SoalResource::getUrl());
    $this->get(SoalResource::getUrl())->assertOk()->assertSee('IPS');
    Livewire::test(ListSoals::class, ['mapel' => $this->mapelLain->id])
        ->assertCanSeeTableRecords([$soal])
        ->assertActionVisible(TestAction::make('edit')->table($soal));

    actingAs(User::factory()->create())->get(SoalResource::getUrl())->assertForbidden();
});
