<?php

use App\Enums\JenisOrangTua;
use App\Enums\JenisSuratKeluar;
use App\Filament\Resources\PenandaTangans\Pages\ManagePenandaTangans;
use App\Filament\Resources\SuratKeluars\Pages\CreateSuratKeluar;
use App\Filament\Resources\SuratKeluars\Pages\ListSuratKeluars;
use App\Models\Kelas;
use App\Models\OrangTua;
use App\Models\PenandaTangan;
use App\Models\Siswa;
use App\Models\SuratKeluar;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\Surat\PenomoranSurat;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Select;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    config(['sekolah.format_nomor_surat' => '{nomor}/{kode}/{instansi}/{bulan}/{tahun}', 'sekolah.kode_instansi_surat' => 'MTs-AT', 'sekolah.tempat_tanda_tangan' => 'Cisurupan']);
    $this->wakasek = PenandaTangan::factory()->create(['nama' => 'Drs. Hidayat', 'jabatan' => 'Wakil Kepala Madrasah Bidang Kesiswaan', 'nip' => '197001011995031001']);
});

/**
 * Answer every Gemini request with the given letter.
 *
 * @param  array<string, string>  $surat
 */
function geminiMenulisSurat(array $surat): void
{
    config(['services.gemini.key' => 'kunci-uji', 'services.gemini.model' => 'gemini-uji']);

    Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode($surat)]]]]]])]);
}

it('numbers outgoing letters per year with the kind code and Roman month', function () {
    $pertama = SuratKeluar::factory()->create(['jenis' => JenisSuratKeluar::Undangan, 'tanggal' => '2026-10-05']);
    $kedua = SuratKeluar::factory()->create(['jenis' => JenisSuratKeluar::Tugas, 'tanggal' => '2026-11-20']);
    $tahunBaru = SuratKeluar::factory()->create(['jenis' => JenisSuratKeluar::Edaran, 'tanggal' => '2027-01-03']);

    expect([$pertama->nomor, $kedua->nomor, $tahunBaru->nomor])->toBe([
        '001/UND/MTs-AT/X/2026',
        '002/ST/MTs-AT/XI/2026',
        '001/SE/MTs-AT/I/2027',
    ])->and(app(PenomoranSurat::class)->pratinjauNomor(JenisSuratKeluar::Undangan, Carbon::parse('2026-12-01')))->toBe('003/UND/MTs-AT/XII/2026');
});

it('keeps the signer as they were when the letter was written', function () {
    $surat = SuratKeluar::factory()->create(['penanda_tangan_id' => $this->wakasek->id]);

    $this->wakasek->update(['nama' => 'Nama Baru', 'jabatan' => 'Kepala Madrasah']);

    expect($surat->fresh())
        ->nama_penanda_tangan->toBe('Drs. Hidayat')
        ->jabatan_penanda_tangan->toBe('Wakil Kepala Madrasah Bidang Kesiswaan');
});

it('writes a letter from the page and shows it in the live preview', function () {
    actingAs(userWithPermissions(['ViewAny:SuratKeluar', 'Create:SuratKeluar', 'Update:SuratKeluar']));

    Livewire::test(CreateSuratKeluar::class)
        ->fillForm([
            'jenis' => JenisSuratKeluar::Pemberitahuan->value,
            'tanggal' => '2026-10-30',
            'perihal' => 'Libur Maulid Nabi',
            'tujuan' => 'Bapak/Ibu Orang Tua/Wali Siswa',
            'penanda_tangan_id' => $this->wakasek->id,
            'isi' => '<p>Diberitahukan bahwa kegiatan belajar diliburkan.</p>',
        ])
        ->assertSeeText(['001/PEMB/MTs-AT/X/2026', 'Libur Maulid Nabi', 'Diberitahukan bahwa kegiatan belajar diliburkan.', 'Drs. Hidayat'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(SuratKeluar::query()->sole())
        ->nomor->toBe('001/PEMB/MTs-AT/X/2026')
        ->jabatan_penanda_tangan->toBe('Wakil Kepala Madrasah Bidang Kesiswaan');
});

it('drafts the letter with Gemini without sending any student data', function () {
    actingAs(userWithPermissions(['ViewAny:SuratKeluar', 'Create:SuratKeluar']));
    $siswa = Siswa::factory()->create(['nama_lengkap' => 'Siswa Rahasia', 'nis' => '990001']);
    geminiMenulisSurat([
        'jenis' => 'keterangan',
        'sifat' => 'penting',
        'perihal' => 'Keterangan Berkelakuan Baik',
        'tujuan' => 'Yang Berkepentingan',
        'lampiran' => '-',
        'isi' => '<p>Menerangkan bahwa {nama_siswa} berkelakuan baik.</p><script>alert(1)</script>',
    ]);

    $halaman = Livewire::test(CreateSuratKeluar::class)
        ->fillForm(['siswa_id' => $siswa->id, 'perintah' => 'Buat surat keterangan berkelakuan baik untuk siswa ini'])
        ->callAction(TestAction::make('buatDenganAi')->schemaComponent('aksiAi'))
        ->assertNotified('Surat dibuat dengan Gemini')
        ->assertSeeText('Menerangkan bahwa Siswa Rahasia berkelakuan baik.');

    expect($halaman->get('data.jenis'))->toBe(JenisSuratKeluar::Keterangan->value)
        ->and($halaman->get('data.perihal'))->toBe('Keterangan Berkelakuan Baik')
        ->and($halaman->get('data.lampiran'))->toBeNull()
        ->and($halaman->get('data.isi'))->not->toContain('<script>');

    Http::assertSent(fn (Request $request): bool => ! str_contains($request->body(), 'Siswa Rahasia') && ! str_contains($request->body(), '990001'));
});

it('tells the user when Gemini cannot write the letter', function () {
    actingAs(userWithPermissions(['ViewAny:SuratKeluar', 'Create:SuratKeluar']));
    config(['services.gemini.key' => 'kunci-uji', 'services.gemini.model' => 'gemini-uji']);
    Http::fake(['*' => Http::response([], 429)]);

    Livewire::test(CreateSuratKeluar::class)
        ->fillForm(['perintah' => 'Buat surat undangan rapat'])
        ->callAction(TestAction::make('buatDenganAi')->schemaComponent('aksiAi'))
        ->assertNotified('Surat belum bisa dibuat dengan AI');
});

it('prints the letter with the student filled in and escaped', function () {
    actingAs(userWithPermissions(['View:SuratKeluar']));
    $siswa = Siswa::factory()->for(Kelas::factory()->create(['nama' => 'IX-A']))->create(['nama_lengkap' => 'Ahmad <b>Fauzi</b>', 'nis' => '250001']);
    OrangTua::factory()->for($siswa)->create(['jenis' => JenisOrangTua::Ayah, 'nama' => 'Budi Santoso']);
    $surat = SuratKeluar::factory()->for($siswa)->create([
        'tanggal' => '2026-10-30',
        'perihal' => 'Panggilan Orang Tua',
        'penanda_tangan_id' => $this->wakasek->id,
        'isi' => '<p>Mohon {nama_orang_tua}, orang tua dari {nama_siswa} ({nis}) kelas {kelas}, hadir.</p>',
    ]);

    get(route('surat.keluar', $surat))
        ->assertOk()
        ->assertSee('Mohon Budi Santoso, orang tua dari Ahmad &lt;b&gt;Fauzi&lt;/b&gt; (250001) kelas IX-A, hadir.', escape: false)
        ->assertSeeText(['001/UND/MTs-AT/X/2026', 'Panggilan Orang Tua', 'Cisurupan, 30 Oktober 2026', 'Wakil Kepala Madrasah Bidang Kesiswaan', 'Drs. Hidayat', 'NIP. 197001011995031001']);

    get(route('surat.keluar', ['surat' => $surat, 'format' => 'pdf']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('forbids printing a letter without permission to view it', function () {
    actingAs(User::factory()->create())
        ->get(route('surat.keluar', SuratKeluar::factory()->create()))
        ->assertForbidden();
});

it('duplicates a letter under the next number', function () {
    actingAs(userWithPermissions(['ViewAny:SuratKeluar', 'Create:SuratKeluar', 'Update:SuratKeluar']));
    $asli = SuratKeluar::factory()->create(['tanggal' => now(), 'perihal' => 'Undangan Rapat']);

    Livewire::test(ListSuratKeluars::class)
        ->callAction(TestAction::make('duplikat')->table($asli));

    $salinan = SuratKeluar::query()->whereKeyNot($asli->id)->sole();

    expect($salinan)
        ->perihal->toBe('Undangan Rapat')
        ->nomor_urut->toBe($asli->nomor_urut + 1);
});

it('is signed by the principal of the academic year the letter is dated in', function () {
    TahunAjaran::factory()->create(['nama' => '2025/2026', 'kepala_madrasah' => 'Kepala Lama', 'nip_kepala_madrasah' => '111']);
    TahunAjaran::factory()->aktif()->create(['nama' => '2026/2027', 'kepala_madrasah' => 'Kepala Baru', 'nip_kepala_madrasah' => '222']);

    $semesterGenap = SuratKeluar::factory()->create(['tanggal' => '2026-03-10']);
    $semesterGanjil = SuratKeluar::factory()->create(['tanggal' => '2026-10-30']);

    expect($semesterGenap->kepalaMadrasah())->toBe(['nama' => 'Kepala Lama', 'nip' => '111'])
        ->and($semesterGanjil->kepalaMadrasah())->toBe(['nama' => 'Kepala Baru', 'nip' => '222'])
        ->and($semesterGanjil->nama_penanda_tangan)->toBeNull();
});

it('prints the additional signer on the left and the principal once, on the right', function () {
    actingAs(userWithPermissions(['View:SuratKeluar']));
    TahunAjaran::factory()->aktif()->create(['nama' => '2026/2027', 'kepala_madrasah' => 'Mumin Mustawan Jamil']);
    $surat = SuratKeluar::factory()->create(['tanggal' => '2026-10-30', 'penanda_tangan_id' => $this->wakasek->id]);

    $html = get(route('surat.keluar', $surat))
        ->assertOk()
        ->assertSeeTextInOrder(['Wakil Kepala Madrasah Bidang Kesiswaan', 'Drs. Hidayat', 'Cisurupan, 30 Oktober 2026', 'Kepala Madrasah', 'Mumin Mustawan Jamil'])
        ->getContent();

    expect(substr_count($html, 'Mumin Mustawan Jamil'))->toBe(1)
        ->and(substr_count($html, 'Cisurupan, 30 Oktober 2026'))->toBe(1);
});

it('never offers or accepts the principal as an additional signer', function () {
    actingAs(userWithPermissions(['ViewAny:SuratKeluar', 'Create:SuratKeluar', 'ViewAny:PenandaTangan', 'Create:PenandaTangan']));
    PenandaTangan::factory()->create(['nama' => 'Kepala Dobel', 'jabatan' => 'Kepala Madrasah']);

    Livewire::test(CreateSuratKeluar::class)
        ->assertFormFieldExists('penanda_tangan_id', fn (Select $field): bool => array_values($field->getOptions()) === ['Wakil Kepala Madrasah Bidang Kesiswaan - Drs. Hidayat']);

    Livewire::test(ManagePenandaTangans::class)
        ->callAction('create', ['nama' => 'Siapa Saja', 'jabatan' => ' kepala madrasah '])
        ->assertHasFormErrors(['jabatan']);
});
