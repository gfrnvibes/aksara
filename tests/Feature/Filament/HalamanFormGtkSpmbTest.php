<?php

use App\Enums\StatusPpdb;
use App\Filament\Resources\Gtks\GtkResource;
use App\Filament\Resources\Gtks\Pages\CreateGtk;
use App\Filament\Resources\Gtks\Pages\EditGtk;
use App\Filament\Resources\Gtks\Pages\ManageGtks;
use App\Filament\Resources\Ppdbs\Pages\CreatePpdb;
use App\Filament\Resources\Ppdbs\Pages\EditPpdb;
use App\Filament\Resources\Ppdbs\Pages\ListPpdbs;
use App\Filament\Resources\Ppdbs\PpdbResource;
use App\Models\Gtk;
use App\Models\Ppdb;
use App\Models\TahunAjaran;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->tahun = TahunAjaran::factory()->aktif()->create();
    actingAs(userWithPermissions(['ViewAny:Gtk', 'View:Gtk', 'Create:Gtk', 'Update:Gtk', 'ViewAny:Ppdb', 'View:Ppdb', 'Create:Ppdb', 'Update:Ppdb']));
});

it('links GTK create and edit actions to full pages and saves edits across tabs', function () {
    $gtk = Gtk::factory()->create();
    Livewire::test(ManageGtks::class)
        ->assertActionHasUrl('create', GtkResource::getUrl('create'))
        ->assertActionHasUrl(TestAction::make('edit')->table($gtk), GtkResource::getUrl('edit', ['record' => $gtk]));
    Livewire::test(CreateGtk::class)->assertSuccessful()->assertSee('Identitas GTK')->assertSee('Pendidikan &amp; Kontak', false);
    Livewire::test(EditGtk::class, ['record' => $gtk->getRouteKey()])->assertSuccessful()
        ->fillForm(['nama' => 'Guru Diperbarui, S.Pd.', 'jabatan' => 'Wali Kelas', 'pendidikan_terakhir' => 'S2', 'alamat' => 'Alamat diperbarui'])
        ->call('save')->assertHasNoFormErrors();
    expect($gtk->refresh()->nama)->toBe('Guru Diperbarui, S.Pd.')->and($gtk->jabatan)->toBe('Wali Kelas')->and($gtk->pendidikan_terakhir)->toBe('S2')->and($gtk->alamat)->toBe('Alamat diperbarui');
});

it('links SPMB actions to full pages and creates and edits data across tabs', function () {
    Livewire::test(ListPpdbs::class)->assertActionHasUrl('create', PpdbResource::getUrl('create'));
    Livewire::test(CreatePpdb::class)->assertSuccessful()->assertSee('Identitas Calon Murid')->assertSee('Berkas Pendaftaran')
        ->fillForm([
            'tahun_ajaran_id' => $this->tahun->id, 'nama_lengkap' => 'Calon Murid', 'nisn' => '0123456789', 'nik' => '3205010101140001',
            'tempat_lahir' => 'Garut', 'tanggal_lahir' => '2014-01-01', 'jenis_kelamin' => 'L', 'alamat' => 'Cisurupan', 'nomor_telepon' => '081234567890',
            'nama_ayah_kandung' => 'Ayah', 'nama_ibu_kandung' => 'Ibu', 'catatan' => 'Periksa dokumen asli',
        ])->call('create')->assertHasNoFormErrors();
    $pendaftar = Ppdb::query()->sole();
    expect($pendaftar->catatan)->toBe('Periksa dokumen asli')->and($pendaftar->nama_ayah_kandung)->toBe('Ayah');
    Livewire::test(ListPpdbs::class)->assertActionHasUrl(TestAction::make('edit')->table($pendaftar), PpdbResource::getUrl('edit', ['record' => $pendaftar]));
    Livewire::test(EditPpdb::class, ['record' => $pendaftar->getRouteKey()])->assertSuccessful()
        ->fillForm(['nama_lengkap' => 'Calon Murid Diperbarui', 'nama_wali' => 'Wali', 'catatan' => 'Berkas diperiksa'])
        ->call('save')->assertHasNoFormErrors();
    expect($pendaftar->refresh()->nama_lengkap)->toBe('Calon Murid Diperbarui')->and($pendaftar->nama_wali)->toBe('Wali')->and($pendaftar->catatan)->toBe('Berkas diperiksa');
});

it('validates required fields on inactive tabs', function () {
    Livewire::test(CreatePpdb::class)->fillForm(['nama_lengkap' => 'Belum lengkap'])->call('create')
        ->assertHasFormErrors(['nisn', 'nik', 'alamat', 'nomor_telepon', 'nama_ayah_kandung', 'nama_ibu_kandung']);
});

it('denies direct create and edit pages without permissions and locks converted SPMB records', function () {
    $gtk = Gtk::factory()->create();
    $pendaftar = Ppdb::factory()->for($this->tahun, 'tahunAjaran')->create();
    $converted = Ppdb::factory()->for($this->tahun, 'tahunAjaran')->status(StatusPpdb::JadiSiswa)->create();
    Livewire::test(EditPpdb::class, ['record' => $converted->getRouteKey()])->assertForbidden();
    actingAs(userWithPermissions(['ViewAny:Gtk', 'ViewAny:Ppdb']));
    Livewire::test(CreateGtk::class)->assertForbidden();
    Livewire::test(EditGtk::class, ['record' => $gtk->getRouteKey()])->assertForbidden();
    Livewire::test(CreatePpdb::class)->assertForbidden();
    Livewire::test(EditPpdb::class, ['record' => $pendaftar->getRouteKey()])->assertForbidden();
});
