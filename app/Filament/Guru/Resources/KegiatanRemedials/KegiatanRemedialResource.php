<?php

namespace App\Filament\Guru\Resources\KegiatanRemedials;

use App\Filament\Guru\Resources\KegiatanRemedials\Pages\ManageKegiatanRemedials;
use App\Models\Gtk;
use App\Models\KegiatanRemedial;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\TahunAjaran;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class KegiatanRemedialResource extends Resource
{
    protected static ?string $model = KegiatanRemedial::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingUp;

    protected static ?string $navigationLabel = 'Remedial & Pengayaan (C6-C7)';

    protected static ?string $modelLabel = 'Remedial / Pengayaan';

    protected static ?string $pluralModelLabel = 'Remedial, Pengayaan & Konseling';

    protected static ?int $navigationSort = 13;

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
            Select::make('gtk_id')
                ->label('Guru Pengampu')
                ->options(fn (): array => Gtk::query()->where('is_aktif', true)->orderBy('nama')->pluck('nama', 'id')->all())
                ->default(fn (): ?int => auth()->user()?->gtk?->id)
                ->searchable()
                ->required(),
            Select::make('kelas_id')
                ->label('Kelas')
                ->options(fn (): array => Kelas::query()->orderBy('nama')->pluck('nama', 'id')->all())
                ->searchable()
                ->required(),
            Select::make('mata_pelajaran_id')
                ->label('Mata Pelajaran')
                ->options(fn (): array => MataPelajaran::query()->orderBy('nama')->pluck('nama', 'id')->all())
                ->searchable()
                ->required(),
            DatePicker::make('tanggal')
                ->label('Tanggal Pelaksanaan')
                ->default(today())
                ->required(),
            Select::make('jenis')
                ->label('Jenis Program')
                ->options(KegiatanRemedial::JENIS)
                ->default('remedial')
                ->required(),
            TextInput::make('jumlah_sasaran')
                ->label('Jumlah Siswa Sasaran')
                ->numeric()
                ->minValue(0)
                ->default(1)
                ->required(),
            TextInput::make('jumlah_hadir')
                ->label('Jumlah Siswa Hadir')
                ->numeric()
                ->minValue(0)
                ->default(1)
                ->required(),
            Textarea::make('catatan')
                ->label('Catatan Materi / Tindak Lanjut')
                ->rows(2)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('tanggal', 'desc')
            ->columns([
                TextColumn::make('tanggal')->label('Tanggal')->date('d/m/Y')->sortable(),
                TextColumn::make('jenis')
                    ->label('Program')
                    ->formatStateUsing(fn (string $state): string => KegiatanRemedial::JENIS[$state] ?? $state),
                TextColumn::make('kelas.nama')->label('Kelas')->sortable(),
                TextColumn::make('mataPelajaran.nama')->label('Mapel')->sortable(),
                TextColumn::make('gtk.nama')->label('Guru')->limit(25),
                TextColumn::make('jumlah_hadir')
                    ->label('Kehadiran')
                    ->formatStateUsing(fn (KegiatanRemedial $record): string => "{$record->jumlah_hadir} / {$record->jumlah_sasaran}"),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageKegiatanRemedials::route('/'),
        ];
    }
}
