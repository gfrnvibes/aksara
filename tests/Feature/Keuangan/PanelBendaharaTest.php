<?php

use App\Filament\Bendahara\Pages\BukuKas;
use App\Filament\Bendahara\Pages\DetailTagihanSiswa;
use App\Filament\Bendahara\Pages\TagihanSiswa as HalamanTagihan;
use App\Models\Gtk;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TagihanSiswa;
use App\Models\TahunAjaran;
use App\Models\TransaksiKeuangan;
use App\Models\User;
use App\Services\Keuangan\LaporanKeuangan;
use App\Services\Keuangan\PencatatanKeuangan;
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
    $this->tahun = TahunAjaran::factory()->aktif()->create();
    $this->siswa = Siswa::factory()->create();
    $this->tagihan = TagihanSiswa::query()->create([
        'siswa_id' => $this->siswa->id, 'tahun_ajaran_id' => $this->tahun->id,
        'nama' => 'SPP', 'periode' => 'November 2026', 'nominal' => 150000,
        'jatuh_tempo' => today()->subDay(), 'pencatat_id' => $this->bendahara->id,
    ]);
    $this->layanan = app(PencatatanKeuangan::class);
    $this->bayar = ['nominal' => 50000, 'tanggal' => today()->toDateString(), 'metode' => 'tunai'];
});

it('renders the finance pages for a treasurer', function () {
    foreach (['/bendahara', '/bendahara/tagihan-siswa', '/bendahara/buku-kas'] as $url) {
        $this->get($url)->assertOk();
    }
});

it('lets an active teacher also be a treasurer without a second account', function () {
    $gtk = Gtk::factory()->denganAkun()->create();
    $gtk->user->assignRole('bendahara');
    actingAs($gtk->user)->get('/guru')->assertOk();
    $this->get('/bendahara')->assertOk();
    $gtk->user->removeRole('bendahara');
    $this->get('/bendahara')->assertForbidden();
    $this->get('/guru')->assertOk();
});

it('rejects unrelated roles and direct financial actions and exports', function () {
    Role::findOrCreate('operator', 'web');
    actingAs(User::factory()->create()->assignRole('operator'));
    $this->get('/bendahara')->assertForbidden();
    $this->get('/bendahara/buku-kas')->assertForbidden();
    $this->get(route('keuangan.laporan', ['mulai' => '2026-01-01', 'selesai' => '2026-12-31']))->assertForbidden();
    expect(fn () => $this->layanan->catat($this->bayar, $this->tagihan->id))->toThrow(HttpException::class);
});

it('records installments once in the ledger and prevents overpayment', function () {
    Livewire::test(DetailTagihanSiswa::class, ['tagihan' => $this->tagihan->id])
        ->callAction(TestAction::make('bayar')->table($this->tagihan), $this->bayar)
        ->assertHasNoActionErrors();
    expect($this->tagihan->fresh()->sisa())->toBe(100000)
        ->and(TransaksiKeuangan::query()->count())->toBe(1);
    expect(fn () => $this->layanan->catat([...$this->bayar, 'nominal' => 100001], $this->tagihan->id))->toThrow(ValidationException::class);
    $this->layanan->catat([...$this->bayar, 'nominal' => 100000], $this->tagihan->id);
    expect($this->tagihan->fresh()->status())->toBe('Lunas');
});

it('preserves canceled payments and restores the amount due', function () {
    $t = $this->layanan->catat($this->bayar, $this->tagihan->id);
    expect(fn () => $this->layanan->batalkanTagihan($this->tagihan->id, 'Salah membuat tagihan'))->toThrow(ValidationException::class);
    Livewire::test(BukuKas::class)->callAction(TestAction::make('batalkan')->table($t), ['alasan' => 'Pembayaran salah siswa'])->assertHasNoActionErrors();
    expect($this->tagihan->fresh()->sisa())->toBe(150000)
        ->and($t->fresh()->alasan_pembatalan)->toBe('Pembayaran salah siswa')
        ->and($t->fresh()->pembatal->id)->toBe($this->bendahara->id)
        ->and(TransaksiKeuangan::query()->count())->toBe(1);
    $this->layanan->batalkanTagihan($this->tagihan->id, 'Tagihan salah periode');
    expect(fn () => $this->layanan->catat($this->bayar, $this->tagihan->id))->toThrow(ValidationException::class);
    $this->get(route('keuangan.kuitansi', $t))->assertOk()->assertSee('DIBATALKAN');
});

it('creates class invoices without duplicates and leaves other classes alone', function () {
    $kelas = Kelas::factory()->create();
    $siswa = Siswa::factory()->for($kelas)->count(2)->create();
    $data = ['tahun_ajaran_id' => $this->tahun->id, 'kelas_id' => $kelas->id, 'siswa_id' => null, 'nama' => 'Kegiatan', 'periode' => 'Semester 1', 'nominal' => 100000, 'jatuh_tempo' => today()->toDateString()];
    foreach ([1, 2] as $ulang) {
        Livewire::test(HalamanTagihan::class)->callAction('buatTagihan', $data)->assertHasNoActionErrors();
    }
    expect(TagihanSiswa::query()->where('nama', 'Kegiatan')->count())->toBe(2)
        ->and(TagihanSiswa::query()->where('nama', 'Kegiatan')->pluck('siswa_id')->sort()->values()->all())->toBe($siswa->modelKeys());
});

it('calculates opening balance and excludes canceled transactions from reports', function () {
    $base = ['jenis' => 'pemasukan', 'kategori' => 'Donasi', 'uraian' => '=SUM(1+1)', 'metode' => 'transfer'];
    $this->layanan->catat([...$base, 'tanggal' => today()->subMonth()->toDateString(), 'nominal' => 200000]);
    $this->layanan->catat([...$base, 'tanggal' => today()->toDateString(), 'nominal' => 100000]);
    $this->layanan->catat([...$base, 'jenis' => 'pengeluaran', 'tanggal' => today()->toDateString(), 'nominal' => 30000]);
    $batal = $this->layanan->catat($this->bayar, $this->tagihan->id);
    $this->layanan->batalkanTransaksi($batal->id, 'Salah mencatat pembayaran');
    $laporan = app(LaporanKeuangan::class)->susun(today()->toDateString(), today()->toDateString());
    expect($laporan)->saldoAwal->toBe(200000)->pemasukan->toBe(100000)->pengeluaran->toBe(30000)->saldoAkhir->toBe(270000)->piutang->toBe(150000);
    $csv = $this->get(route('keuangan.laporan', ['mulai' => today()->toDateString(), 'selesai' => today()->toDateString()]))->assertOk()->streamedContent();
    expect($csv)->toContain("'=SUM(1+1)", 'Dibatalkan', 'Salah mencatat pembayaran');
    $this->get(route('keuangan.kuitansi', $batal))->assertOk();
    actingAs(User::factory()->create())->get(route('keuangan.kuitansi', $batal))->assertForbidden();
});

it('rejects invalid amounts and future transaction dates', function (array $ubah) {
    expect(fn () => $this->layanan->catat([...$this->bayar, ...$ubah], $this->tagihan->id))->toThrow(ValidationException::class);
})->with([[['nominal' => 0]], [['nominal' => -10]], [['nominal' => 1.5]], [['tanggal' => '2099-01-01']]]);

it('lists exactly one row per invoice name with totals unaffected by installments', function () {
    $siswa = Siswa::factory()->create();
    $kedua = TagihanSiswa::query()->create([
        'siswa_id' => $siswa->id, 'tahun_ajaran_id' => $this->tahun->id, 'nama' => 'SPP',
        'periode' => 'Desember 2026', 'nominal' => 200000, 'jatuh_tempo' => today(), 'pencatat_id' => $this->bendahara->id,
    ]);
    $lain = TagihanSiswa::query()->create([
        'siswa_id' => $siswa->id, 'tahun_ajaran_id' => $this->tahun->id, 'nama' => 'Kegiatan',
        'periode' => 'Semester 1', 'nominal' => 75000, 'jatuh_tempo' => today(), 'pencatat_id' => $this->bendahara->id,
    ]);
    $this->layanan->catat($this->bayar, $this->tagihan->id);
    $this->layanan->catat($this->bayar, $this->tagihan->id);
    $batal = $this->layanan->catat($this->bayar, $kedua->id);
    $this->layanan->batalkanTransaksi($batal->id, 'Salah pencatatan pembayaran');
    $url = DetailTagihanSiswa::getUrl(['tagihan' => $this->tagihan->id]);
    $page = Livewire::test(HalamanTagihan::class)
        ->assertCountTableRecords(2)
        ->assertTableColumnStateSet('total_nominal', 350000, $this->tagihan)
        ->assertTableColumnStateSet('total_terbayar', 100000, $this->tagihan)
        ->assertTableColumnStateSet('total_sisa', 250000, $this->tagihan)
        ->assertTableColumnStateSet('jumlah_siswa', 2, $this->tagihan)
        ->assertTableActionHasUrl('lihatDetail', $url, $this->tagihan);
    $row = $page->instance()->getTableRecords()->firstWhere('nama', 'SPP');
    expect($page->instance()->getTable()->getRecordUrl($row))->toBe($url);
    $page->searchTable('SPP')->sortTable('total_nominal', 'desc')->assertCountTableRecords(1);
    $page->sortTable('nama')->assertCountTableRecords(1);
    $this->get($url)->assertOk()->assertSee('Detail Tagihan: SPP');
    Livewire::test(DetailTagihanSiswa::class, ['tagihan' => $this->tagihan->id])
        ->assertCanSeeTableRecords([$this->tagihan, $kedua])->assertCanNotSeeTableRecords([$lain])
        ->assertSee('350.000')->assertSee('250.000');
});

it('excludes canceled invoices from totals while keeping their recipients in the detail history', function () {
    $this->layanan->batalkanTagihan($this->tagihan->id, 'Tagihan salah periode');
    Livewire::test(HalamanTagihan::class)
        ->assertCountTableRecords(1)
        ->assertTableColumnStateSet('total_nominal', 0, $this->tagihan)
        ->assertTableColumnStateSet('jumlah_batal', 1, $this->tagihan);
    Livewire::test(DetailTagihanSiswa::class, ['tagihan' => $this->tagihan->id])
        ->assertCanSeeTableRecords([$this->tagihan])->assertSee('Dibatalkan');
});

it('keeps one name across academic years and recomputes detail totals for its filters', function () {
    $tahun = TahunAjaran::factory()->create(['nama' => '2027/2028']);
    $berikut = TagihanSiswa::query()->create([
        'siswa_id' => $this->siswa->id, 'tahun_ajaran_id' => $tahun->id, 'nama' => 'SPP',
        'periode' => 'Juli 2027', 'nominal' => 275000, 'jatuh_tempo' => today(), 'pencatat_id' => $this->bendahara->id,
    ]);
    Livewire::test(HalamanTagihan::class)->assertCountTableRecords(1)
        ->assertTableColumnStateSet('total_nominal', 425000, $this->tagihan)
        ->assertTableColumnStateSet('jumlah_siswa', 1, $this->tagihan);
    Livewire::test(DetailTagihanSiswa::class, ['tagihan' => $this->tagihan->id])
        ->filterTable('tahun_ajaran_id', $tahun->id)
        ->assertCanSeeTableRecords([$berikut])->assertCanNotSeeTableRecords([$this->tagihan])
        ->assertSee('275.000')->assertDontSee('425.000');
    actingAs(User::factory()->create())->get(DetailTagihanSiswa::getUrl(['tagihan' => $berikut->id]))->assertForbidden();
});
