<x-filament-panels::page>
    <x-filament::section heading="Laporan Keuangan Sekolah" icon="heroicon-o-calendar-days" description="Pilih periode laporan. Klik kartu pemasukan atau pengeluaran untuk membuka rincian Buku Kas.">
        <div class="mb-4 flex flex-wrap gap-2">
            <x-filament::button size="sm" color="gray" outlined wire:click="pilihPeriode('bulan-ini')">Bulan ini</x-filament::button>
            <x-filament::button size="sm" color="gray" outlined wire:click="pilihPeriode('bulan-lalu')">Bulan lalu</x-filament::button>
            <x-filament::button size="sm" color="gray" outlined wire:click="pilihPeriode('tahun-ini')">Tahun ini</x-filament::button>
        </div>
        <form wire:submit="terapkan" class="flex flex-wrap items-end gap-4">
            <div><label for="mulai" class="mb-2 block text-sm font-medium">Dari tanggal</label><x-filament::input.wrapper prefix-icon="heroicon-o-calendar-days"><x-filament::input id="mulai" type="date" wire:model="mulai" required /></x-filament::input.wrapper>@error('mulai')<p class="text-danger-600">{{ $message }}</p>@enderror</div>
            <div><label for="selesai" class="mb-2 block text-sm font-medium">Sampai tanggal</label><x-filament::input.wrapper prefix-icon="heroicon-o-calendar-days"><x-filament::input id="selesai" type="date" wire:model="selesai" required /></x-filament::input.wrapper>@error('selesai')<p class="text-danger-600">{{ $message }}</p>@enderror</div>
            <x-filament::button type="submit" icon="heroicon-o-funnel" wire:loading.attr="disabled">Tampilkan</x-filament::button>
            <x-filament::button tag="a" color="gray" icon="heroicon-o-arrow-down-tray" :href="route('keuangan.laporan', ['mulai' => $periodeMulai, 'selesai' => $periodeSelesai])">Ekspor CSV</x-filament::button>
        </form>
    </x-filament::section>

    @livewire(\App\Filament\Bendahara\Widgets\StatistikKeuangan::class, ['mulai' => $periodeMulai, 'selesai' => $periodeSelesai], key('statistik-'.$periodeMulai.'-'.$periodeSelesai))
    @livewire(\App\Filament\Bendahara\Widgets\ArusKas::class, ['mulai' => $periodeMulai, 'selesai' => $periodeSelesai], key('arus-kas-'.$periodeMulai.'-'.$periodeSelesai))

    {{ $this->table }}

    <x-filament::section heading="Catatan Laporan" icon="heroicon-o-information-circle" collapsible collapsed>
        <p class="text-sm text-gray-500 dark:text-gray-400">Sisa tagihan mencakup seluruh periode dan belum termasuk saldo kas. Saldo awal dihitung dari transaksi sebelum periode terpilih. Transaksi yang dibatalkan tidak dihitung dalam saldo; alasan dan petugas pembatalan dapat ditelusuri di Buku Kas serta ekspor CSV.</p>
    </x-filament::section>
</x-filament-panels::page>
