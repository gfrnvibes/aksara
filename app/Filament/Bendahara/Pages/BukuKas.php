<?php

namespace App\Filament\Bendahara\Pages;

use App\Filament\Bendahara\Widgets\RingkasanBukuKas;
use App\Models\TransaksiKeuangan;
use App\Services\Keuangan\PencatatanKeuangan;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

class BukuKas extends HalamanKeuangan implements HasTable
{
    use InteractsWithTable;

    /** @var array<string, mixed>|null */
    #[Url(as: 'filters')]
    public ?array $tableFilters = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Buku Kas';

    public function getSubheading(): ?string
    {
        return 'Pantau pemasukan dan pengeluaran sekolah. Klik transaksi untuk melihat rincian dan riwayatnya.';
    }

    /** @return array<Component> */
    public static function fieldPembayaran(): array
    {
        return [
            DatePicker::make('tanggal')->default(today())->maxDate(today())->required(),
            Select::make('metode')->options(['tunai' => 'Tunai', 'transfer' => 'Transfer'])->default('tunai')->required(),
            TextInput::make('referensi')->label('Referensi / No. Bukti')->maxLength(255),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('catat')->label('Catat Transaksi')->icon(Heroicon::OutlinedPlus)->schema([
            Select::make('jenis')->options(['pemasukan' => 'Pemasukan', 'pengeluaran' => 'Pengeluaran'])->required(),
            TextInput::make('kategori')->placeholder('BOS / Donasi / Operasional / Gaji')->maxLength(255)->required(),
            TextInput::make('uraian')->maxLength(255)->required(),
            TextInput::make('nominal')->numeric()->integer()->minValue(1)->maxValue(999999999999)->prefix('Rp')->required(),
            ...self::fieldPembayaran(),
        ])->action(function (array $data): void {
            app(PencatatanKeuangan::class)->catat($data);
            Notification::make()->success()->title('Transaksi tercatat')->send();
        })];
    }

    protected function getHeaderWidgets(): array
    {
        return [RingkasanBukuKas::class];
    }

    public function getWidgetData(): array
    {
        $query = $this->getFilteredTableQuery()->whereNull('dibatalkan_at');

        return ['ringkasan' => [
            'masuk' => (int) (clone $query)->where('jenis', 'pemasukan')->sum('nominal'),
            'keluar' => (int) (clone $query)->where('jenis', 'pengeluaran')->sum('nominal'),
        ]];
    }

    #[On('filter-ringkasan-kas')]
    public function filterRingkasanKas(string $jenis = ''): void
    {
        abort_unless(static::canAccess(), 403);
        abort_unless(in_array($jenis, ['', 'pemasukan', 'pengeluaran'], true), 422);
        $this->tableFilters['jenis']['value'] = $jenis === '' ? null : $jenis;
        $this->tableFilters['status']['value'] = 'sah';
        $this->updatedTableFilters();
        $this->flushCachedTableRecords();
    }

    protected function detailAction(): Action
    {
        return Action::make('detail')->label('Lihat Detail')->icon(Heroicon::OutlinedEye)
            ->modalHeading('Detail Transaksi')->modalWidth('2xl')->modalSubmitAction(false)->modalCancelActionLabel('Tutup')
            ->schema(fn (TransaksiKeuangan $record): array => [
                Section::make('Informasi Transaksi')->columns(2)->schema([
                    TextEntry::make('uraian')->state($record->uraian)->columnSpanFull(),
                    TextEntry::make('nominal')->state($record->nominal)->money('IDR', decimalPlaces: 0)->weight('bold')->color($record->jenis === 'pemasukan' ? 'success' : 'gray'),
                    TextEntry::make('jenis')->state(ucfirst($record->jenis))->badge()->color($record->jenis === 'pemasukan' ? 'success' : 'gray'),
                    TextEntry::make('tanggal')->state($record->tanggal)->date('d M Y'),
                    TextEntry::make('kategori')->state($record->kategori),
                    TextEntry::make('metode')->state(ucfirst($record->metode)),
                    TextEntry::make('referensi')->label('Referensi / No. Bukti')->state($record->referensi)->placeholder('-'),
                ]),
                Section::make('Jejak Pencatatan')->columns(2)->schema([
                    TextEntry::make('nomor')->label('Nomor Transaksi')->state($record->nomor)->copyable()->columnSpanFull(),
                    TextEntry::make('pencatat')->label('Dicatat Oleh')->state($record->pencatat->name),
                    TextEntry::make('waktu')->label('Waktu Catat')->state($record->getAttribute('created_at'))->dateTime('d M Y H:i'),
                    TextEntry::make('status')->state($record->dibatalkan_at ? 'Dibatalkan' : 'Sah')->badge()->color($record->dibatalkan_at ? 'danger' : 'success'),
                ]),
                Section::make('Pembatalan')->visible($record->dibatalkan_at !== null)->columns(2)->schema([
                    TextEntry::make('alasan')->state($record->alasan_pembatalan)->columnSpanFull(),
                    TextEntry::make('pembatal')->label('Dibatalkan Oleh')->state($record->pembatal?->name),
                    TextEntry::make('waktu_batal')->label('Waktu Pembatalan')->state($record->dibatalkan_at)->dateTime('d M Y H:i'),
                ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table->defaultPaginationPageOption(25)->paginationPageOptions([10, 25, 50, 100])->query(TransaksiKeuangan::query()->with(['pencatat', 'pembatal']))->defaultSort('id', 'desc')
            ->recordAction('detail')
            ->columns([
                TextColumn::make('tanggal')->date('d M Y')->sortable()->width('110px'),
                TextColumn::make('uraian')->label('Transaksi')->searchable()->limit(48)->tooltip(fn (TransaksiKeuangan $record): string => $record->uraian)->weight('medium'),
                TextColumn::make('kategori')->searchable()->limit(22)->tooltip(fn (TransaksiKeuangan $record): string => $record->kategori)->toggleable(),
                TextColumn::make('jenis')->badge()->formatStateUsing(fn (string $state): string => $state === 'pemasukan' ? 'Masuk' : 'Keluar')
                    ->icon(fn (string $state): Heroicon => $state === 'pemasukan' ? Heroicon::OutlinedArrowDownLeft : Heroicon::OutlinedArrowUpRight)
                    ->color(fn (string $state): string => $state === 'pemasukan' ? 'success' : 'gray'),
                TextColumn::make('nominal')->money('IDR', decimalPlaces: 0)->sortable()->alignEnd()->weight('bold')
                    ->color(fn (TransaksiKeuangan $record): string => $record->dibatalkan_at ? 'gray' : ($record->jenis === 'pemasukan' ? 'success' : 'gray')),
                TextColumn::make('status')->state(fn (TransaksiKeuangan $record): string => $record->dibatalkan_at ? 'Dibatalkan' : 'Sah')->badge()
                    ->color(fn (string $state): string => $state === 'Sah' ? 'success' : 'danger'),
                TextColumn::make('nomor')->label('No. Bukti')->searchable()->copyable()->limit(13)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('metode')->badge()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('referensi')->searchable()->limit(25)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('pencatat.name')->label('Dicatat Oleh')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Waktu Catat')->dateTime('d M Y H:i')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('alasan_pembatalan')->limit(30)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('pembatal.name')->label('Dibatalkan Oleh')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('dibatalkan_at')->dateTime('d M Y H:i')->toggleable(isToggledHiddenByDefault: true),
            ])->filters([
                SelectFilter::make('jenis')->options(['pemasukan' => 'Pemasukan', 'pengeluaran' => 'Pengeluaran']),
                SelectFilter::make('metode')->options(['tunai' => 'Tunai', 'transfer' => 'Transfer']),
                SelectFilter::make('status')->options(['sah' => 'Sah', 'batal' => 'Dibatalkan'])->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                    'sah' => $query->whereNull('dibatalkan_at'), 'batal' => $query->whereNotNull('dibatalkan_at'), default => $query
                }),
                Filter::make('periode')->schema([DatePicker::make('mulai'), DatePicker::make('selesai')->afterOrEqual('mulai')])->query(fn (Builder $query, array $data) => $query
                    ->when($data['mulai'] ?? null, fn (Builder $q, $tanggal) => $q->whereDate('tanggal', '>=', $tanggal))
                    ->when($data['selesai'] ?? null, fn (Builder $q, $tanggal) => $q->whereDate('tanggal', '<=', $tanggal))),
            ])->recordActions([ActionGroup::make([
                $this->detailAction(),
                Action::make('kuitansi')->label('Cetak Bukti')->icon(Heroicon::OutlinedPrinter)->url(fn (TransaksiKeuangan $record) => route('keuangan.kuitansi', $record))->openUrlInNewTab(),
                Action::make('batalkan')->color('danger')->visible(fn (TransaksiKeuangan $record) => $record->dibatalkan_at === null)->requiresConfirmation()
                    ->modalDescription('Pembatalan mengeluarkan transaksi dari saldo, membuka kembali sisa tagihan atau status gaji. Catatan tetap tersimpan.')
                    ->schema([Textarea::make('alasan')->required()->minLength(5)->maxLength(1000)])
                    ->action(fn (TransaksiKeuangan $record, array $data) => app(PencatatanKeuangan::class)->batalkanTransaksi($record->id, $data['alasan'])),
            ])->label('Aksi Transaksi')->tooltip('Lihat detail, cetak, atau batalkan')->icon(Heroicon::OutlinedEllipsisHorizontal)])->emptyStateHeading('Belum ada transaksi keuangan')->emptyStateIcon(Heroicon::OutlinedBanknotes);
    }
}
