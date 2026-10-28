<?php

namespace App\Filament\Resources\MutasiSiswas;

use App\Enums\JenisMutasi;
use App\Enums\StatusSiswa;
use App\Filament\Actions\CetakSuratActions;
use App\Filament\Resources\MutasiSiswas\Pages\ManageMutasiSiswas;
use App\Models\Kelas;
use App\Models\MutasiSiswa;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class MutasiSiswaResource extends Resource
{
    protected static ?string $model = MutasiSiswa::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Buku Induk';

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'Mutasi Siswa';

    protected static ?string $pluralModelLabel = 'Mutasi Siswa';

    protected static ?string $slug = 'mutasi-siswa';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                ToggleButtons::make('jenis')
                    ->options(JenisMutasi::class)
                    ->default(JenisMutasi::Pindah)
                    ->inline()
                    ->live()
                    ->disabledOn('edit')
                    ->required()
                    ->columnSpanFull(),
                Select::make('siswa_id')
                    ->label('Siswa')
                    ->relationship(
                        'siswa',
                        'nama_lengkap',
                        fn (Builder $query, Get $get): Builder => $query
                            ->when(
                                static::jenis($get)?->meninggalkanSekolah(),
                                fn (Builder $query) => $query->where('status', StatusSiswa::Aktif),
                            )
                            ->orderBy('nama_lengkap'),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Siswa $record): string => "{$record->nis} - {$record->nama_lengkap}")
                    ->searchable(['nama_lengkap', 'nis', 'nisn'])
                    ->helperText(fn (Get $get): string => static::jenis($get) === JenisMutasi::Masuk
                        ? 'Tambahkan dulu biodata siswa pindahan di Data Siswa, lalu catat mutasinya di sini.'
                        : 'Hanya siswa aktif.')
                    ->live()
                    ->afterStateUpdated(fn (?int $state, Set $set) => $set('kelas_id', $state ? Siswa::query()->find($state)?->kelas_id : null))
                    ->disabledOn('edit')
                    ->required(),
                DatePicker::make('tanggal')
                    ->default(now())
                    ->required(),
                Select::make('tahun_ajaran_id')
                    ->label('Tahun Ajaran')
                    ->relationship('tahunAjaran', 'nama', fn (Builder $query): Builder => $query->orderByDesc('nama'))
                    ->default(fn (): ?int => TahunAjaran::query()->where('is_aktif', true)->value('id')),
                Select::make('kelas_id')
                    ->label(fn (Get $get): string => static::jenis($get) === JenisMutasi::Masuk ? 'Diterima di Kelas' : 'Kelas Saat Mutasi')
                    ->relationship('kelas', 'nama', fn (Builder $query, ?MutasiSiswa $record): Builder => Kelas::batasiYangTersedia($query, $record?->kelas_id)),
                TextInput::make('sekolah')
                    ->label(fn (Get $get): string => static::jenis($get)?->labelSekolah() ?? 'Sekolah')
                    ->visible(fn (Get $get): bool => static::jenis($get) !== JenisMutasi::Keluar)
                    ->maxLength(255),
                TextInput::make('alasan')
                    ->maxLength(255),
                TextInput::make('nomor_surat')
                    ->label('Nomor Surat')
                    ->maxLength(255),
                Textarea::make('keterangan')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['siswa', 'kelas', 'tahunAjaran']))
            ->defaultSort('tanggal', 'desc')
            ->columns([
                TextColumn::make('tanggal')
                    ->date('d-m-Y')
                    ->sortable(),
                TextColumn::make('jenis')
                    ->badge(),
                TextColumn::make('siswa.nis')
                    ->label('NIS')
                    ->searchable(),
                TextColumn::make('siswa.nama_lengkap')
                    ->label('Nama Siswa')
                    ->searchable(),
                TextColumn::make('kelas.nama')
                    ->label('Kelas')
                    ->placeholder('-'),
                TextColumn::make('sekolah')
                    ->label('Sekolah Asal / Tujuan')
                    ->placeholder('-')
                    ->wrap(),
                TextColumn::make('alasan')
                    ->placeholder('-')
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('nomor_surat')
                    ->label('No. Surat')
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('jenis')
                    ->options(JenisMutasi::class),
                SelectFilter::make('tahun_ajaran_id')
                    ->label('Tahun Ajaran')
                    ->relationship('tahunAjaran', 'nama'),
            ])
            ->recordActions([
                CetakSuratActions::suratPindah(),
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Bila siswa masih tercatat pindah/keluar karena mutasi ini, statusnya dikembalikan menjadi Aktif.'),
            ]);
    }

    protected static function jenis(Get $get): ?JenisMutasi
    {
        $jenis = $get('jenis');

        return $jenis instanceof JenisMutasi ? $jenis : JenisMutasi::tryFrom((string) $jenis);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMutasiSiswas::route('/'),
        ];
    }
}
