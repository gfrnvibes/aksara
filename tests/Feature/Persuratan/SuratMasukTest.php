<?php

use App\Enums\StatusSuratMasuk;
use App\Filament\Resources\SuratMasuks\Pages\ManageSuratMasuks;
use App\Models\SuratMasuk;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('numbers the incoming letters of each year in the agenda', function () {
    $pertama = SuratMasuk::factory()->create(['tanggal_diterima' => '2026-03-01']);
    $kedua = SuratMasuk::factory()->create(['tanggal_diterima' => '2026-09-01']);
    $tahunBaru = SuratMasuk::factory()->create(['tanggal_diterima' => '2027-01-02']);

    expect([$pertama->labelAgenda(), $kedua->labelAgenda(), $tahunBaru->labelAgenda()])->toBe(['001/2026', '002/2026', '001/2027']);
});

it('records an incoming letter from the page', function () {
    actingAs(userWithPermissions(['ViewAny:SuratMasuk', 'Create:SuratMasuk']));

    Livewire::test(ManageSuratMasuks::class)
        ->callAction('create', [
            'tanggal_diterima' => '2026-10-30',
            'pengirim' => 'Kankemenag Kabupaten Garut',
            'nomor_surat' => 'B-1234/Kk.10.05/PP.00/10/2026',
            'perihal' => 'Undangan Rapat Kepala Madrasah',
        ])
        ->assertHasNoFormErrors();

    expect(SuratMasuk::query()->sole())
        ->labelAgenda()->toBe('001/2026')
        ->status->toBe(StatusSuratMasuk::Baru);
});

it('passes a letter on and then closes it', function () {
    actingAs(userWithPermissions(['ViewAny:SuratMasuk', 'Update:SuratMasuk']));
    $surat = SuratMasuk::factory()->create();

    Livewire::test(ManageSuratMasuks::class)
        ->callAction(TestAction::make('disposisi')->table($surat), [
            'disposisi_kepada' => 'Wakil Kepala Madrasah Bidang Kurikulum',
            'disposisi_instruksi' => 'Mohon dihadiri',
            'disposisi_tanggal' => '2026-10-31',
        ])
        ->assertHasNoFormErrors();

    expect($surat->fresh())
        ->status->toBe(StatusSuratMasuk::Didisposisikan)
        ->disposisi_kepada->toBe('Wakil Kepala Madrasah Bidang Kurikulum');

    Livewire::test(ManageSuratMasuks::class)
        ->callAction(TestAction::make('selesai')->table($surat));

    expect($surat->fresh()->status)->toBe(StatusSuratMasuk::Selesai);
});

it('keeps incoming letters away from users without permission', function () {
    actingAs(User::factory()->create());

    Livewire::test(ManageSuratMasuks::class)->assertForbidden();
});
