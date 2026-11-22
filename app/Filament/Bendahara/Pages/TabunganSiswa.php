<?php

namespace App\Filament\Bendahara\Pages;

use App\Models\Siswa;
use App\Models\TabunganSiswa as Tabungan;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TabunganSiswa extends HalamanKeuangan implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Buku Tabungan Siswa';

    protected static ?string $navigationLabel = 'Buku Tabungan';

    public function getSubheading(): ?string
    {
        return 'Catat transaksi setoran dan penarikan tabungan siswa. Data ini otomatis tampil pada Portal Siswa / Orang Tua di aplikasi mobile.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('catatTransaksi')
                ->label('Catat Setor / Tarik')
                ->icon(Heroicon::OutlinedPlus)
                ->visible(fn (): bool => static::bolehKelola())
                ->schema([
                    Select::make('siswa_id')
                        ->label('Siswa')
                        ->options(fn (): array => Siswa::query()
                            ->aktif()
                            ->with('kelas')
                            ->orderBy('nama_lengkap')
                            ->get()
                            ->mapWithKeys(fn (Siswa $s): array => [
                                $s->id => $s->nis.' - '.$s->nama_lengkap.($s->kelas ? ' ('.$s->kelas->nama.')' : ''),
                            ])
                            ->all())
                        ->searchable()
                        ->required(),
                    DatePicker::make('tanggal')
                        ->label('Tanggal Transaksi')
                        ->default(today())
                        ->maxDate(today())
                        ->required(),
                    Select::make('jenis')
                        ->label('Jenis Transaksi')
                        ->options([
                            'setor' => 'Setor (Menabung)',
                            'tarik' => 'Tarik (Penarikan)',
                        ])
                        ->default('setor')
                        ->required(),
                    TextInput::make('nominal')
                        ->label('Nominal')
                        ->numeric()
                        ->integer()
                        ->minValue(100)
                        ->prefix('Rp')
                        ->required(),
                    TextInput::make('keterangan')
                        ->label('Keterangan')
                        ->maxLength(255)
                        ->placeholder('Opsional, misal: Setoran rutin mingguan'),
                ])
                ->action(function (array $data): void {
                    $siswaId = (int) $data['siswa_id'];
                    $nominal = (int) $data['nominal'];
                    $jenis = (string) $data['jenis'];

                    DB::transaction(function () use ($siswaId, $nominal, $jenis, $data): void {
                        Siswa::query()->whereKey($siswaId)->lockForUpdate()->firstOrFail();

                        if ($jenis === 'tarik') {
                            $saldo = Tabungan::saldoUntuk($siswaId);
                            if ($nominal > $saldo) {
                                throw ValidationException::withMessages([
                                    'nominal' => 'Saldo tabungan siswa tidak mencukupi (saldo saat ini: Rp '.number_format($saldo, 0, ',', '.').').',
                                ]);
                            }
                        }

                        Tabungan::query()->create([
                            'siswa_id' => $siswaId,
                            'pencatat_id' => auth()->id(),
                            'tanggal' => $data['tanggal'],
                            'jenis' => $jenis,
                            'nominal' => $nominal,
                            'keterangan' => $data['keterangan'] ?? null,
                        ]);
                    });

                    Notification::make()
                        ->title('Transaksi tabungan berhasil dicatat')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Tabungan::query()
                    ->with(['siswa.kelas', 'pencatat'])
                    ->orderByDesc('tanggal')
                    ->orderByDesc('id')
            )
            ->columns([
                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('siswa.nis')
                    ->label('NIS')
                    ->searchable(),
                TextColumn::make('siswa.nama_lengkap')
                    ->label('Nama Siswa')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('siswa.kelas.nama')
                    ->label('Kelas')
                    ->placeholder('-'),
                TextColumn::make('jenis')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'setor' ? 'Setor' : 'Tarik')
                    ->color(fn (string $state): string => $state === 'setor' ? 'success' : 'danger'),
                TextColumn::make('nominal')
                    ->label('Nominal')
                    ->money('IDR', locale: 'id')
                    ->sortable(),
                TextColumn::make('saldo_saat_ini')
                    ->label('Saldo Siswa')
                    ->state(fn (Tabungan $record): int => Tabungan::saldoUntuk($record->siswa_id))
                    ->money('IDR', locale: 'id'),
                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->placeholder('-')
                    ->limit(40),
                TextColumn::make('pencatat.name')
                    ->label('Pencatat')
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('jenis')
                    ->label('Jenis Transaksi')
                    ->options([
                        'setor' => 'Setor',
                        'tarik' => 'Tarik',
                    ]),
                SelectFilter::make('siswa_id')
                    ->label('Siswa')
                    ->options(fn (): array => Siswa::query()
                        ->orderBy('nama_lengkap')
                        ->pluck('nama_lengkap', 'id')
                        ->all())
                    ->searchable(),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('Hapus')
                    ->visible(fn (): bool => static::bolehKelola()),
            ])
            ->defaultSort('tanggal', 'desc');
    }
}
