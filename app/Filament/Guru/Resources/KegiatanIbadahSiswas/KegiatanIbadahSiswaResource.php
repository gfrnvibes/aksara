<?php

namespace App\Filament\Guru\Resources\KegiatanIbadahSiswas;

use App\Filament\Guru\Resources\KegiatanIbadahSiswas\Pages\ManageKegiatanIbadahSiswas;
use App\Models\KegiatanIbadahSiswa;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class KegiatanIbadahSiswaResource extends Resource
{
    protected static ?string $model = KegiatanIbadahSiswa::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static ?string $navigationLabel = 'Pembiasaan Ibadah (A1)';

    protected static ?string $modelLabel = 'Pembiasaan Ibadah';

    protected static ?string $pluralModelLabel = 'Pembiasaan Ibadah Siswa';

    protected static ?int $navigationSort = 12;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->gtk()->aktif()->exists();
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canAccess();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('tahun_ajaran_id')
                ->label('Tahun Ajaran')
                ->options(fn (): array => TahunAjaran::query()->orderByDesc('nama')->pluck('nama', 'id')->all())
                ->default(fn (): ?int => TahunAjaran::query()->where('is_aktif', true)->value('id'))
                ->required(),
            Select::make('kelas_id')
                ->label('Kelas')
                ->options(fn (): array => Kelas::query()->orderBy('nama')->pluck('nama', 'id')->all())
                ->required()
                ->searchable(),
            DatePicker::make('tanggal')
                ->label('Tanggal')
                ->default(today())
                ->required(),
            Select::make('jenis')
                ->label('Jenis Kegiatan Ibadah')
                ->options(KegiatanIbadahSiswa::JENIS)
                ->default('sholat_berjamaah')
                ->required(),
            TextInput::make('jumlah_siswa')
                ->label('Jumlah Siswa di Kelas')
                ->numeric()
                ->minValue(1)
                ->required(),
            TextInput::make('jumlah_ikut')
                ->label('Jumlah Siswa yang Mengikuti')
                ->numeric()
                ->minValue(0)
                ->required(),
            TextInput::make('alasan_ketidakikutsertaan')
                ->label('Keterangan Alasan yang Tidak Mengikuti (Syarat Tingkat 4)')
                ->placeholder('Contoh: 2 siswa sakit, 1 siswi berhalangan')
                ->maxLength(255)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('tanggal', 'desc')
            ->columns([
                TextColumn::make('tanggal')->label('Tanggal')->date('d/m/Y')->sortable(),
                TextColumn::make('kelas.nama')->label('Kelas')->sortable(),
                TextColumn::make('jenis')
                    ->label('Kegiatan')
                    ->formatStateUsing(fn (string $state): string => KegiatanIbadahSiswa::JENIS[$state] ?? $state),
                TextColumn::make('jumlah_ikut')
                    ->label('Keikutsertaan')
                    ->formatStateUsing(fn (KegiatanIbadahSiswa $record): string => "{$record->jumlah_ikut} / {$record->jumlah_siswa}"),
                TextColumn::make('alasan_ketidakikutsertaan')
                    ->label('Alasan Tidak Ikut')
                    ->placeholder('-')
                    ->limit(35),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageKegiatanIbadahSiswas::route('/'),
        ];
    }
}
