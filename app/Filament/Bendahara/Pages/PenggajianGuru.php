<?php

namespace App\Filament\Bendahara\Pages;

use App\Enums\JenisGtk;
use App\Models\GajiGuru;
use App\Models\Gtk;
use App\Services\Keuangan\PenggajianGuru as LayananGaji;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PenggajianGuru extends HalamanKeuangan implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Penggajian Guru';

    public function getSubheading(): ?string
    {
        return 'Ambil guru dari data GTK, susun gaji bulanan, lalu catat pembayaran sebagai pengeluaran di Buku Kas.';
    }

    /** @return array<Component> */
    protected function fieldNominal(): array
    {
        return [
            TextInput::make('gaji_pokok')->label('Gaji Pokok')->numeric()->integer()->minValue(0)->maxValue(99999999999)->prefix('Rp')->default(0)->live(onBlur: true)->required(),
            TextInput::make('tunjangan')->numeric()->integer()->minValue(0)->maxValue(99999999999)->prefix('Rp')->default(0)->live(onBlur: true)->required(),
            TextInput::make('potongan')->numeric()->integer()->minValue(0)->maxValue(99999999999)->prefix('Rp')->default(0)->live(onBlur: true)->required(),
            TextEntry::make('gaji_bersih')->label('Gaji Bersih')->color('gray')->state(fn (Get $get): string => 'Rp '.number_format((int) $get('gaji_pokok') + (int) $get('tunjangan') - (int) $get('potongan'), 0, ',', '.')),
            Textarea::make('catatan')->label('Rincian Tunjangan / Potongan')->helperText('Tuliskan penjelasan komponen agar slip mudah diperiksa.')->maxLength(2000),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('buatGaji')->label('Buat Gaji Guru')->schema([
            Select::make('gtk_id')->label('Guru dari GTK')->options(fn () => Gtk::query()->aktif()->where('jenis_gtk', JenisGtk::Guru)->orderBy('nama')->get()->mapWithKeys(fn (Gtk $guru): array => [$guru->id => $guru->nama.' | NIP: '.($guru->nip ?: '-')]))->searchable()->required()
                ->helperText('Hanya guru aktif. Nama dan NIP disalin otomatis, tanpa perlu akun login guru.'),
            TextInput::make('periode')->label('Periode Gaji')->type('month')->default(today()->format('Y-m'))->required()->rules(['date_format:Y-m'])->helperText('Satu catatan gaji per guru per bulan.'),
            ...$this->fieldNominal(),
        ])->action(function (array $data): void {
            app(LayananGaji::class)->simpan($data);
            Notification::make()->success()->title('Gaji guru disiapkan')->body('Belum memengaruhi Buku Kas sampai pembayaran dicatat.')->send();
        })];
    }

    public function table(Table $table): Table
    {
        return $table->defaultPaginationPageOption(25)->paginationPageOptions([10, 25, 50, 100])->query(GajiGuru::query()->with('pencatat')->withExists('pembayaran'))->defaultSort('periode', 'desc')
            ->columns([
                TextColumn::make('periode')->label('Periode')->sortable()->searchable(),
                TextColumn::make('nama_guru')->label('Guru')->searchable(),
                TextColumn::make('gaji_pokok')->label('Gaji Pokok')->money('IDR'),
                TextColumn::make('tunjangan')->money('IDR'),
                TextColumn::make('potongan')->color('danger')->money('IDR'),
                TextColumn::make('bersih')->label('Gaji Bersih')->color('gray')->state(fn (GajiGuru $record): int => $record->bersih())->money('IDR')->weight('bold'),
                TextColumn::make('status')->state(fn (GajiGuru $record): string => $record->sudahDibayar() ? 'Dibayar' : 'Belum Dibayar')->badge()->color(fn (string $state): string => $state === 'Dibayar' ? 'success' : 'danger'),
                TextColumn::make('pencatat.name')->label('Disiapkan Oleh')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('catatan')->wrap()->toggleable(isToggledHiddenByDefault: true),
            ])->filters([
                SelectFilter::make('periode')->options(fn () => GajiGuru::query()->orderByDesc('periode')->distinct()->pluck('periode', 'periode')),
                SelectFilter::make('status')->options(['dibayar' => 'Dibayar', 'belum' => 'Belum Dibayar'])->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                    'dibayar' => $query->whereHas('pembayaran'), 'belum' => $query->whereDoesntHave('pembayaran'), default => $query,
                }),
            ])->recordActions([
                Action::make('ubah')->label('Ubah Komponen')->visible(fn (GajiGuru $record): bool => ! $record->sudahDibayar())
                    ->fillForm(fn (GajiGuru $record): array => $record->only(['gaji_pokok', 'tunjangan', 'potongan', 'catatan']))
                    ->schema($this->fieldNominal())->action(function (GajiGuru $record, array $data): void {
                        app(LayananGaji::class)->simpan([...$data, 'gtk_id' => $record->gtk_id, 'periode' => $record->periode], $record->id);
                        Notification::make()->success()->title('Komponen gaji diperbarui')->send();
                    }),
                Action::make('bayar')->label('Catat Pembayaran')->visible(fn (GajiGuru $record): bool => ! $record->sudahDibayar())
                    ->modalHeading(fn (GajiGuru $record): string => 'Pembayaran Gaji '.$record->nama_guru)
                    ->modalDescription(fn (GajiGuru $record): string => 'Periode '.$record->periode.' - Rp '.number_format($record->bersih(), 0, ',', '.').'. Catat setelah pembayaran dilakukan. Tindakan ini tidak mengirim transfer bank.')
                    ->schema(BukuKas::fieldPembayaran())->action(function (GajiGuru $record, array $data): void {
                        app(LayananGaji::class)->bayar($record->id, $data);
                        Notification::make()->success()->title('Pembayaran gaji tercatat di Buku Kas')->send();
                    }),
                Action::make('slip')->label('Cetak Slip')->icon(Heroicon::OutlinedPrinter)->url(fn (GajiGuru $record): string => route('keuangan.slip-gaji', $record))->openUrlInNewTab(),
            ])->emptyStateHeading('Belum ada penggajian guru')->emptyStateDescription('Pilih Buat Gaji Guru untuk mengambil guru dari data GTK.');
    }
}
