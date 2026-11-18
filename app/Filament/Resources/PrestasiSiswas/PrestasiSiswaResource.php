<?php

namespace App\Filament\Resources\PrestasiSiswas;

use App\Filament\Resources\PrestasiSiswas\Pages\ManagePrestasiSiswas;
use App\Models\PrestasiSiswa;
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

class PrestasiSiswaResource extends Resource
{
    protected static ?string $model = PrestasiSiswa::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|UnitEnum|null $navigationGroup = 'Kesiswaan';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Prestasi Siswa';

    protected static ?string $pluralModelLabel = 'Prestasi Siswa';

    protected static ?string $slug = 'prestasi-siswa';

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
                    ->options(fn (): array => Siswa::query()->aktif()->orderBy('nama_lengkap')->get()->mapWithKeys(fn (Siswa $s): array => [$s->id => $s->nis.' - '.$s->nama_lengkap])->all())
                    ->searchable()
                    ->required(),
                DatePicker::make('tanggal')
                    ->label('Tanggal')
                    ->default(today())
                    ->maxDate(today())
                    ->required(),
                Select::make('tingkat')
                    ->label('Tingkat')
                    ->options([
                        'madrasah' => 'Madrasah / Sekolah',
                        'kecamatan' => 'Kecamatan',
                        'kabupaten' => 'Kabupaten / Kota',
                        'provinsi' => 'Provinsi',
                        'nasional' => 'Nasional',
                        'internasional' => 'Internasional',
                    ])
                    ->default('kabupaten')
                    ->required(),
                TextInput::make('nama_prestasi')
                    ->label('Nama Lomba / Prestasi')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('peringkat')
                    ->label('Peringkat / Juara')
                    ->placeholder('Juara 1 / Medali Emas')
                    ->maxLength(50),
                TextInput::make('penyelenggara')
                    ->label('Penyelenggara')
                    ->maxLength(255),
                Textarea::make('keterangan')
                    ->label('Keterangan')
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
                TextColumn::make('nama_prestasi')
                    ->label('Prestasi')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('peringkat')
                    ->label('Peringkat')
                    ->placeholder('-'),
                TextColumn::make('tingkat')
                    ->label('Tingkat')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                TextColumn::make('penyelenggara')
                    ->label('Penyelenggara')
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('tahun_ajaran_id')
                    ->label('Tahun Ajaran')
                    ->options(fn (): array => TahunAjaran::query()->orderByDesc('nama')->pluck('nama', 'id')->all()),
                SelectFilter::make('tingkat')
                    ->options([
                        'madrasah' => 'Madrasah / Sekolah',
                        'kecamatan' => 'Kecamatan',
                        'kabupaten' => 'Kabupaten / Kota',
                        'provinsi' => 'Provinsi',
                        'nasional' => 'Nasional',
                        'internasional' => 'Internasional',
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
            'index' => ManagePrestasiSiswas::route('/'),
        ];
    }
}
