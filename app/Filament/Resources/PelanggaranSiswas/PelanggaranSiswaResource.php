<?php

namespace App\Filament\Resources\PelanggaranSiswas;

use App\Filament\Resources\PelanggaranSiswas\Pages\ManagePelanggaranSiswas;
use App\Models\PelanggaranSiswa;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class PelanggaranSiswaResource extends Resource
{
    protected static ?string $model = PelanggaranSiswa::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static string|UnitEnum|null $navigationGroup = 'Kesiswaan';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Pelanggaran Siswa';

    protected static ?string $pluralModelLabel = 'Buku Kedisiplinan';

    protected static ?string $slug = 'buku-kedisiplinan';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tahun_ajaran_id')
                    ->label('Tahun Ajaran')
                    ->options(fn (): array => TahunAjaran::query()->orderByDesc('nama')->pluck('nama', 'id')->all())
                    ->default(fn (): ?int => TahunAjaran::query()->where('is_aktif', true)->value('id'))
                    ->required(),
                Select::make('siswa_id')
                    ->label('Siswa')
                    ->options(fn (): array => Siswa::query()->orderBy('nama_lengkap')->get()->mapWithKeys(fn (Siswa $s): array => [$s->id => $s->nis.' - '.$s->nama_lengkap])->all())
                    ->searchable()
                    ->required(),
                DatePicker::make('tanggal')
                    ->label('Tanggal')
                    ->default(today())
                    ->maxDate(today())
                    ->required(),
                Select::make('kategori')
                    ->label('Kategori')
                    ->options([
                        'ringan' => 'Ringan',
                        'sedang' => 'Sedang',
                        'berat' => 'Berat',
                    ])
                    ->default('ringan')
                    ->required(),
                TextInput::make('pelanggaran')
                    ->label('Bentuk Pelanggaran')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('poin')
                    ->label('Poin')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(1000)
                    ->default(5)
                    ->required(),
                Select::make('status')
                    ->label('Status Pembinaan')
                    ->options([
                        'proses' => 'Proses Pembinaan',
                        'selesai' => 'Selesai',
                    ])
                    ->default('proses')
                    ->required(),
                Textarea::make('tindak_lanjut')
                    ->label('Tindak Lanjut / Catatan BK')
                    ->rows(3)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('tanggal', 'desc')
            ->columns([
                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('siswa.nama_lengkap')
                    ->label('Siswa')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('siswa.kelas.nama')
                    ->label('Kelas')
                    ->placeholder('-'),
                TextColumn::make('pelanggaran')
                    ->label('Pelanggaran')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('kategori')
                    ->label('Kategori')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'berat' => 'danger',
                        'sedang' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                TextColumn::make('poin')
                    ->label('Poin')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'selesai' ? 'success' : 'warning')
                    ->formatStateUsing(fn (string $state): string => $state === 'selesai' ? 'Selesai' : 'Proses'),
            ])
            ->filters([
                SelectFilter::make('tahun_ajaran_id')
                    ->label('Tahun Ajaran')
                    ->options(fn (): array => TahunAjaran::query()->orderByDesc('nama')->pluck('nama', 'id')->all()),
                SelectFilter::make('kategori')
                    ->options([
                        'ringan' => 'Ringan',
                        'sedang' => 'Sedang',
                        'berat' => 'Berat',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'proses' => 'Proses Pembinaan',
                        'selesai' => 'Selesai',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePelanggaranSiswas::route('/'),
        ];
    }
}
