<?php

use App\Filament\Bendahara\Pages\BukuKas;
use App\Filament\Bendahara\Pages\DetailTagihanSiswa;
use App\Filament\Bendahara\Pages\PenggajianGuru;
use App\Filament\Bendahara\Pages\RingkasanKeuangan;
use App\Filament\Bendahara\Pages\TagihanSiswa;
use App\Filament\Bendahara\Widgets\ArusKas;
use App\Filament\Bendahara\Widgets\StatistikKeuangan;
use App\Models\Siswa;
use App\Models\TagihanSiswa as Tagihan;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\Keuangan\PencatatanKeuangan;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Role::findOrCreate('bendahara', 'web');
    $this->user = User::factory()->create()->assignRole('bendahara');
    actingAs($this->user);
    Filament::setCurrentPanel('bendahara');
});

it('uses 25 rows per page on every treasurer table', function (string $page) {
    Livewire::test($page)->assertSet('tableRecordsPerPage', 25);
})->with([BukuKas::class, TagihanSiswa::class, PenggajianGuru::class, RingkasanKeuangan::class]);

it('uses 25 rows in invoice details', function () {
    $tagihan = Tagihan::query()->create([
        'siswa_id' => Siswa::factory()->create()->id, 'tahun_ajaran_id' => TahunAjaran::factory()->create()->id,
        'nama' => 'SPP', 'periode' => 'November', 'nominal' => 100000, 'jatuh_tempo' => today(), 'pencatat_id' => $this->user->id,
    ]);
    Livewire::test(DetailTagihanSiswa::class, ['tagihan' => $tagihan->id])->assertSet('tableRecordsPerPage', 25);
});

it('renders native category totals and applies periods without querying invalid draft dates', function () {
    $layanan = app(PencatatanKeuangan::class);
    $base = ['tanggal' => today()->toDateString(), 'jenis' => 'pemasukan', 'kategori' => 'Donasi', 'uraian' => 'Bantuan sekolah', 'nominal' => 100000, 'metode' => 'tunai'];
    $layanan->catat($base);
    $layanan->catat($base);
    $layanan->catat([...$base, 'jenis' => 'pengeluaran', 'nominal' => 30000]);
    $batal = $layanan->catat($base);
    $layanan->batalkanTransaksi($batal->id, 'Transaksi salah input');
    $page = Livewire::test(RingkasanKeuangan::class)->assertCountTableRecords(2)->assertSee('200.000')->assertSee('30.000');
    $page->searchTable('Donasi')->sortTable('total', 'desc')->assertCountTableRecords(2);
    $page->set('mulai', '')->call('terapkan')->assertHasErrors(['mulai'])->assertCountTableRecords(2);
    $page->call('pilihPeriode', 'bulan-lalu')->assertHasNoErrors()->assertCountTableRecords(0);
    $page->call('pilihPeriode', 'bulan-ini')->assertCountTableRecords(2)->assertSee('Grafik Arus Kas');
});

it('charts only valid cash movements for the selected period', function () {
    $layanan = app(PencatatanKeuangan::class);
    $base = ['tanggal' => today()->toDateString(), 'jenis' => 'pemasukan', 'kategori' => 'Donasi', 'uraian' => 'Bantuan sekolah', 'nominal' => 100000, 'metode' => 'tunai'];
    $layanan->catat($base);
    $layanan->catat([...$base, 'jenis' => 'pengeluaran', 'nominal' => 30000]);
    $batal = $layanan->catat($base);
    $layanan->batalkanTransaksi($batal->id, 'Pembatalan untuk koreksi');
    $chart = Livewire::test(ArusKas::class, ['mulai' => today()->toDateString(), 'selesai' => today()->toDateString()]);
    $data = \Livewire\invade($chart->instance())->getData();
    expect($data['datasets'][0]['data'])->toBe([100000])->and($data['datasets'][1]['data'])->toBe([30000]);
});

it('opens cash details with the dates and type from a statistic card', function () {
    $mulai = today()->startOfMonth()->toDateString();
    $selesai = today()->toDateString();
    $widget = Livewire::test(StatistikKeuangan::class, ['mulai' => $mulai, 'selesai' => $selesai]);
    $stats = \Livewire\invade($widget->instance())->getStats();
    parse_str(parse_url($stats[1]->getUrl(), PHP_URL_QUERY), $query);
    Livewire::withQueryParams($query)->test(BukuKas::class)
        ->assertSet('tableFilters.jenis.value', 'pemasukan')
        ->assertSet('tableFilters.periode.mulai', $mulai)
        ->assertSet('tableFilters.periode.selesai', $selesai);
});

it('opens complete transaction details while keeping the cash entry unchanged', function () {
    $transaksi = app(PencatatanKeuangan::class)->catat([
        'tanggal' => today()->toDateString(), 'jenis' => 'pemasukan', 'kategori' => 'Donasi',
        'uraian' => 'Bantuan operasional sekolah dengan uraian lengkap untuk diperiksa',
        'nominal' => 1250000, 'metode' => 'transfer', 'referensi' => 'BUKTI-LENGKAP-001',
    ]);
    $page = Livewire::test(BukuKas::class)
        ->mountAction(TestAction::make('detail')->table($transaksi))
        ->assertActionMounted(TestAction::make('detail')->table($transaksi));
    $html = \Livewire\invade($page->instance())->getMountedActionSchema()->toHtml();
    expect($html)->toContain($transaksi->uraian, $transaksi->nomor, 'BUKTI-LENGKAP-001');
    expect($transaksi->fresh()->nominal)->toBe(1250000)->and($transaksi->fresh()->dibatalkan_at)->toBeNull();
});
