<?php

use App\Enums\JenisGtk;
use App\Filament\Bendahara\Pages\PenggajianGuru;
use App\Models\GajiGuru;
use App\Models\Gtk;
use App\Models\TransaksiKeuangan;
use App\Models\User;
use App\Services\Keuangan\LaporanKeuangan;
use App\Services\Keuangan\PencatatanKeuangan;
use App\Services\Keuangan\PenggajianGuru as LayananGaji;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Role::findOrCreate('bendahara', 'web');
    $this->bendahara = User::factory()->create()->assignRole('bendahara');
    actingAs($this->bendahara);
    Filament::setCurrentPanel('bendahara');
    $this->guru = Gtk::factory()->create(['nama' => 'Guru Penggajian', 'nip' => '198501012010012001']);
    $this->data = ['gtk_id' => $this->guru->id, 'periode' => today()->format('Y-m'), 'gaji_pokok' => 2000000, 'tunjangan' => 300000, 'potongan' => 100000, 'catatan' => 'Tunjangan wali kelas dan potongan koperasi'];
    $this->layanan = app(LayananGaji::class);
    $this->bayar = ['tanggal' => today()->toDateString(), 'metode' => 'transfer', 'referensi' => 'TRF-001'];
});

it('renders the separate payroll page and creates a draft from GTK without a login account', function () {
    $this->get('/bendahara/penggajian-guru')->assertOk();
    Livewire::test(PenggajianGuru::class)->callAction('buatGaji', $this->data)->assertHasNoActionErrors();
    $gaji = GajiGuru::query()->sole();
    expect($gaji->nama_guru)->toBe($this->guru->nama)->and($gaji->nip)->toBe($this->guru->nip)
        ->and($gaji->bersih())->toBe(2200000)->and($gaji->sudahDibayar())->toBeFalse()
        ->and(TransaksiKeuangan::query()->count())->toBe(0);
    $this->get(route('keuangan.slip-gaji', $gaji))->assertOk()->assertSee('DRAF - BELUM DIBAYAR')->assertSee('2.200.000');
});

it('rejects duplicate months, inactive GTK and non-teaching staff', function () {
    $this->layanan->simpan($this->data);
    expect(fn () => $this->layanan->simpan($this->data))->toThrow(ValidationException::class);
    $lain = Gtk::factory()->create(['is_aktif' => false]);
    expect(fn () => $this->layanan->simpan([...$this->data, 'gtk_id' => $lain->id]))->toThrow(ValidationException::class);
    $tendik = Gtk::factory()->create(['jenis_gtk' => JenisGtk::TenagaKependidikan]);
    expect(fn () => $this->layanan->simpan([...$this->data, 'gtk_id' => $tendik->id]))->toThrow(ValidationException::class);
    expect(GajiGuru::query()->count())->toBe(1);
});

it('pays exactly the net amount into the ledger and prevents a second payment', function () {
    $gaji = $this->layanan->simpan($this->data);
    Livewire::test(PenggajianGuru::class)->callAction(TestAction::make('bayar')->table($gaji), $this->bayar)->assertHasNoActionErrors();
    $t = TransaksiKeuangan::query()->sole();
    expect($t->gaji_guru_id)->toBe($gaji->id)->and($t->nominal)->toBe(2200000)->and($t->jenis)->toBe('pengeluaran')
        ->and($gaji->fresh()->sudahDibayar())->toBeTrue();
    $laporan = app(LaporanKeuangan::class)->susun(today()->toDateString(), today()->toDateString());
    expect($laporan['pengeluaran'])->toBe(2200000);
    expect(fn () => $this->layanan->bayar($gaji->id, $this->bayar))->toThrow(ValidationException::class);
    expect(fn () => $this->layanan->simpan([...$this->data, 'gaji_pokok' => 2500000], $gaji->id))->toThrow(ValidationException::class);
    $this->get(route('keuangan.slip-gaji', $gaji))->assertOk()->assertSee('DIBAYAR')->assertSee('TRF-001')->assertSee($t->nomor);
});

it('preserves snapshots and allows correction after a logged cancellation', function () {
    $gaji = $this->layanan->simpan($this->data);
    $this->guru->update(['nama' => 'Nama Guru Berubah', 'nip' => '198601012011012001']);
    $t = $this->layanan->bayar($gaji->id, $this->bayar);
    app(PencatatanKeuangan::class)->batalkanTransaksi($t->id, 'Koreksi komponen tunjangan');
    expect($gaji->fresh()->sudahDibayar())->toBeFalse();
    $this->layanan->simpan([...$this->data, 'tunjangan' => 500000], $gaji->id);
    $tBaru = $this->layanan->bayar($gaji->id, $this->bayar);
    expect($gaji->fresh()->nama_guru)->toBe('Guru Penggajian')->and($gaji->fresh()->nip)->toBe('198501012010012001')
        ->and($tBaru->nominal)->toBe(2400000)->and($gaji->transaksi()->count())->toBe(2);
    $this->get(route('keuangan.slip-gaji', $gaji))->assertOk()->assertSee('Koreksi komponen tunjangan')->assertSee('Dibatalkan');
    expect(app(LaporanKeuangan::class)->susun(today()->toDateString(), today()->toDateString())['pengeluaran'])->toBe(2400000);
    expect($this->guru->keteranganPemakaian())->toContain('riwayat penggajian');
});

it('edits draft components through the page without changing its teacher or month', function () {
    $gaji = $this->layanan->simpan($this->data);
    Livewire::test(PenggajianGuru::class)->callAction(TestAction::make('ubah')->table($gaji), ['gaji_pokok' => 2500000, 'tunjangan' => 300000, 'potongan' => 100000])->assertHasNoActionErrors();
    expect($gaji->fresh()->bersih())->toBe(2700000);
    expect(fn () => $this->layanan->simpan([...$this->data, 'periode' => '2026-01'], $gaji->id))->toThrow(ValidationException::class);
});

it('protects payroll records and slips from ordinary teachers and unrelated users', function () {
    $gaji = $this->layanan->simpan($this->data);
    $gtk = Gtk::factory()->denganAkun()->create();
    actingAs($gtk->user)->get('/bendahara/penggajian-guru')->assertForbidden();
    $this->get(route('keuangan.slip-gaji', $gaji))->assertForbidden();
    expect(fn () => $this->layanan->bayar($gaji->id, $this->bayar))->toThrow(HttpException::class);
    expect(fn () => $this->layanan->simpan($this->data))->toThrow(HttpException::class);
});

it('rejects invalid salary components and malformed months', function (array $ubah) {
    expect(fn () => $this->layanan->simpan([...$this->data, ...$ubah]))->toThrow(ValidationException::class);
})->with([[['potongan' => 2300000]], [['gaji_pokok' => -1]], [['tunjangan' => 2.5]], [['periode' => '2026-13']]]);
