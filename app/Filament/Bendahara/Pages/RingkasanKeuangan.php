<?php

namespace App\Filament\Bendahara\Pages;

use App\Models\TransaksiKeuangan;
use App\Models\User;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

class RingkasanKeuangan extends Dashboard implements HasTable
{
    use InteractsWithTable;

    protected static ?string $title = 'Ringkasan Keuangan';

    protected static ?string $navigationLabel = 'Ringkasan & Laporan';

    protected string $view = 'filament.bendahara.ringkasan';

    #[Url]
    public string $mulai = '';

    #[Url]
    public string $selesai = '';

    #[Locked]
    public string $periodeMulai = '';

    #[Locked]
    public string $periodeSelesai = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->bolehKelolaKeuangan();
    }

    public function mount(): void
    {
        $this->mulai = $this->mulai ?: today()->startOfMonth()->toDateString();
        $this->selesai = $this->selesai ?: today()->toDateString();
        $this->terapkan();
    }

    public function terapkan(): void
    {
        $this->validate(['mulai' => ['required', 'date_format:Y-m-d'], 'selesai' => ['required', 'date_format:Y-m-d', 'after_or_equal:mulai']]);
        $this->periodeMulai = $this->mulai;
        $this->periodeSelesai = $this->selesai;
        if (isset($this->table)) {
            $this->resetPage();
            $this->flushCachedTableRecords();
        }
    }

    public function pilihPeriode(string $periode): void
    {
        [$this->mulai, $this->selesai] = match ($periode) {
            'bulan-lalu' => [today()->subMonthNoOverflow()->startOfMonth()->toDateString(), today()->subMonthNoOverflow()->endOfMonth()->toDateString()],
            'tahun-ini' => [today()->startOfYear()->toDateString(), today()->toDateString()],
            default => [today()->startOfMonth()->toDateString(), today()->toDateString()],
        };
        $this->terapkan();
    }

    public function table(Table $table): Table
    {
        return $table->defaultPaginationPageOption(25)->paginationPageOptions([10, 25, 50, 100])
            ->query(function () {
                $rekap = TransaksiKeuangan::query()->whereNull('dibatalkan_at')
                    ->whereDate('tanggal', '>=', $this->periodeMulai)->whereDate('tanggal', '<=', $this->periodeSelesai)
                    ->select('jenis', 'kategori')->selectRaw('min(id) as id, sum(nominal) as total, count(*) as jumlah_transaksi')->groupBy('jenis', 'kategori');

                return TransaksiKeuangan::query()->joinSub($rekap, 'rekap', 'rekap.id', '=', 'transaksi_keuangans.id')
                    ->select('transaksi_keuangans.*', 'rekap.total', 'rekap.jumlah_transaksi');
            })
            ->heading('Rekap per Kategori')->description('Transaksi sah dalam periode terpilih. Gunakan pencarian dan pengurutan untuk menelusuri kategori.')
            ->defaultSort('kategori')->columns([
                TextColumn::make('jenis')->label('Jenis')->badge()->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->icon(fn (string $state): Heroicon => $state === 'pemasukan' ? Heroicon::OutlinedArrowDownLeft : Heroicon::OutlinedArrowUpRight)
                    ->color(fn (string $state): string => $state === 'pemasukan' ? 'success' : 'gray'),
                TextColumn::make('kategori')->searchable(['transaksi_keuangans.kategori'])->sortable(['transaksi_keuangans.kategori']),
                TextColumn::make('jumlah_transaksi')->label('Transaksi')->numeric()->sortable(),
                TextColumn::make('total')->label('Jumlah')->money('IDR')->sortable()->weight('bold')
                    ->color(fn (TransaksiKeuangan $record): string => $record->jenis === 'pemasukan' ? 'success' : 'gray'),
            ])->emptyStateHeading('Belum ada transaksi pada periode ini')->emptyStateIcon(Heroicon::OutlinedBanknotes);
    }
}
