<?php

use App\Filament\Resources\PenandaTangans\Pages\ManagePenandaTangans;
use App\Filament\Resources\SuratKeluars\Pages\CreateSuratKeluar;
use App\Models\Gtk;
use App\Models\PenandaTangan;
use App\Models\SuratKeluar;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Select;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    actingAs(userWithPermissions(['ViewAny:SuratKeluar', 'Create:SuratKeluar', 'ViewAny:PenandaTangan', 'Create:PenandaTangan', 'Update:PenandaTangan']));
    $this->guru = Gtk::factory()->create(['nama' => 'Dede Siti Wahidah, S.Pd.', 'nip' => '198001012005012001']);
});

it('takes the name and NIP of a signer from Data GTK, with one row for each of a teacher\'s titles', function () {
    foreach (['Bendahara', 'Ketua Panitia'] as $jabatan) {
        Livewire::test(ManagePenandaTangans::class)
            ->callAction('create', ['gtk_id' => $this->guru->id, 'nama' => $this->guru->nama, 'jabatan' => $jabatan])
            ->assertHasNoFormErrors();
    }

    expect(PenandaTangan::query()->where('gtk_id', $this->guru->id)->pluck('jabatan')->all())->toEqualCanonicalizing(['Bendahara', 'Ketua Panitia'])
        ->and(PenandaTangan::query()->where('gtk_id', $this->guru->id)->pluck('nip')->unique()->all())->toBe(['198001012005012001']);
});

it('fills in the name and NIP when a GTK is chosen', function () {
    Livewire::test(ManagePenandaTangans::class)
        ->mountAction('create')
        ->fillForm(['gtk_id' => $this->guru->id])
        ->assertSchemaStateSet(['nama' => 'Dede Siti Wahidah, S.Pd.', 'nip' => '198001012005012001'], 'mountedActionSchema0');
});

it('keeps foundation staff as a typed-in signer without a GTK', function () {
    Livewire::test(ManagePenandaTangans::class)
        ->callAction('create', ['nama' => 'H. Ahmad Yani', 'jabatan' => 'Ketua Yayasan'])
        ->assertHasNoFormErrors();

    expect(PenandaTangan::query()->where('nama', 'H. Ahmad Yani')->sole()->gtk_id)->toBeNull();
});

it('follows a corrected GTK but leaves saved letters as they were signed', function () {
    $penandaTangan = PenandaTangan::factory()->create(['gtk_id' => $this->guru->id, 'jabatan' => 'Bendahara']);
    $surat = SuratKeluar::factory()->create(['penanda_tangan_id' => $penandaTangan->id]);

    $this->guru->update(['nama' => 'Dede Siti Wahidah, M.Pd.', 'nip' => '198001012005012009']);

    expect($penandaTangan->refresh())
        ->nama->toBe('Dede Siti Wahidah, M.Pd.')
        ->nip->toBe('198001012005012009')
        ->and($surat->refresh()->nama_penanda_tangan)->toBe('Dede Siti Wahidah, S.Pd.');
});

it('keeps the signer with their last known name when the GTK is deleted', function () {
    $penandaTangan = PenandaTangan::factory()->create(['gtk_id' => $this->guru->id, 'jabatan' => 'Bendahara']);

    $this->guru->delete();

    expect($penandaTangan->refresh())
        ->gtk_id->toBeNull()
        ->nama->toBe('Dede Siti Wahidah, S.Pd.');
});

it('adds a GTK as a signer from the letter form', function () {
    Livewire::test(CreateSuratKeluar::class)
        ->assertFormFieldExists('penanda_tangan_id')
        ->callAction(TestAction::make('createOption')->schemaComponent('penanda_tangan_id', 'form'), [
            'gtk_id' => $this->guru->id,
            'nama' => $this->guru->nama,
            'jabatan' => 'Bendahara',
        ])
        ->assertHasNoFormErrors();

    expect(PenandaTangan::query()->where('gtk_id', $this->guru->id)->sole()->jabatan)->toBe('Bendahara');
});

it('offers only active GTK', function () {
    $nonaktif = Gtk::factory()->create(['nama' => 'Sudah Pensiun', 'is_aktif' => false]);

    Livewire::test(ManagePenandaTangans::class)
        ->mountAction('create')
        ->assertFormFieldExists('gtk_id', 'mountedActionSchema0', function (Select $select) use ($nonaktif): bool {
            return array_key_exists($this->guru->id, $select->getOptions()) && ! array_key_exists($nonaktif->id, $select->getOptions());
        });
});
