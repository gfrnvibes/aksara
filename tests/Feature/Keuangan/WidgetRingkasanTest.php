<?php

use App\Filament\Bendahara\Pages\BukuKas;
use App\Filament\Bendahara\Pages\DetailTagihanSiswa;
use App\Filament\Bendahara\Widgets\RingkasanBukuKas;
use App\Filament\Bendahara\Widgets\RingkasanDetailTagihan;
use App\Models\Siswa;
use App\Models\TagihanSiswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\Keuangan\PencatatanKeuangan;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Role::findOrCreate('bendahara', 'web');
    $this->user = User::factory()->create()->assignRole('bendahara');
    actingAs($this->user);
    Filament::setCurrentPanel('bendahara');
    $this->layanan = app(PencatatanKeuangan::class);
});

it('filters cash from the widget event without losing the period and recalculates the widget data', function () {
    $base = ['tanggal' => today()->toDateString(), 'jenis' => 'pemasukan', 'kategori' => 'Donasi', 'uraian' => 'Bantuan', 'nominal' => 100000, 'metode' => 'tunai'];
    $masuk = $this->layanan->catat($base);
    $keluar = $this->layanan->catat([...$base, 'jenis' => 'pengeluaran', 'nominal' => 30000]);
    $batal = $this->layanan->catat($base);
    $this->layanan->batalkanTransaksi($batal->id, 'Salah transaksi');
    $page = Livewire::test(BukuKas::class)->filterTable('periode', ['mulai' => today()->toDateString(), 'selesai' => today()->toDateString()]);
    expect($page->instance()->getWidgetData()['ringkasan'])->toBe(['masuk' => 100000, 'keluar' => 30000]);
    $page->dispatch('filter-ringkasan-kas', jenis: 'pengeluaran')
        ->assertSet('tableFilters.status.value', 'sah')
        ->assertSet('tableFilters.periode.mulai', today()->toDateString())
        ->assertCanSeeTableRecords([$keluar])->assertCanNotSeeTableRecords([$masuk, $batal]);
    expect($page->instance()->getWidgetData()['ringkasan'])->toBe(['masuk' => 0, 'keluar' => 30000]);
    $page->dispatch('filter-ringkasan-kas', jenis: '')->assertCanSeeTableRecords([$masuk, $keluar])->assertCanNotSeeTableRecords([$batal]);
});

it('filters paid installments and outstanding invoices without changing the invoice scope', function () {
    $tahun = TahunAjaran::factory()->create();
    $data = ['tahun_ajaran_id' => $tahun->id, 'nama' => 'SPP', 'periode' => 'November', 'nominal' => 100000, 'jatuh_tempo' => today(), 'pencatat_id' => $this->user->id];
    $cicil = TagihanSiswa::query()->create([...$data, 'siswa_id' => Siswa::factory()->create()->id]);
    $lunas = TagihanSiswa::query()->create([...$data, 'siswa_id' => Siswa::factory()->create()->id]);
    $belum = TagihanSiswa::query()->create([...$data, 'siswa_id' => Siswa::factory()->create()->id]);
    $lain = TagihanSiswa::query()->create([...$data, 'nama' => 'Kegiatan', 'siswa_id' => Siswa::factory()->create()->id]);
    $this->layanan->catat(['tanggal' => today()->toDateString(), 'nominal' => 40000, 'metode' => 'tunai'], $cicil->id);
    $this->layanan->catat(['tanggal' => today()->toDateString(), 'nominal' => 100000, 'metode' => 'tunai'], $lunas->id);
    $page = Livewire::test(DetailTagihanSiswa::class, ['tagihan' => $cicil->id])->filterTable('tahun_ajaran_id', $tahun->id);
    expect($page->instance()->getWidgetData()['ringkasan']['total_sisa'])->toBe(160000);
    $page->dispatch('filter-ringkasan-tagihan', status: 'terbayar')
        ->assertCanSeeTableRecords([$cicil, $lunas])->assertCanNotSeeTableRecords([$belum, $lain])
        ->assertSet('tableFilters.tahun_ajaran_id.value', $tahun->id);
    expect($page->instance()->getWidgetData()['ringkasan']['total_terbayar'])->toBe(140000);
    $page->dispatch('filter-ringkasan-tagihan', status: 'aktif')
        ->assertCanSeeTableRecords([$cicil, $belum])->assertCanNotSeeTableRecords([$lunas, $lain]);
    $page->dispatch('filter-ringkasan-tagihan', status: 'berlaku')->assertCountTableRecords(3);
});

it('renders the native statistics widgets and prevents access without a finance role', function () {
    Livewire::test(RingkasanBukuKas::class, ['ringkasan' => ['masuk' => 200000, 'keluar' => 50000]])
        ->assertSee('Rp 200.000')->assertSee('Rp 150.000')->assertSee('fi-wi-stats-overview');
    Livewire::test(RingkasanDetailTagihan::class, ['ringkasan' => ['jumlah_siswa' => 3, 'total_nominal' => 300000, 'total_terbayar' => 140000, 'total_sisa' => 160000]])
        ->assertSee('Rp 160.000')->assertSee('filter-ringkasan-tagihan');
    actingAs(User::factory()->create());
    expect(RingkasanBukuKas::canView())->toBeFalse()->and(RingkasanDetailTagihan::canView())->toBeFalse();
    Livewire::test(RingkasanBukuKas::class)->assertForbidden();
});
