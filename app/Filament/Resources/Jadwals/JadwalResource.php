<?php

namespace App\Filament\Resources\Jadwals;

use App\Enums\Semester;
use App\Enums\StatusJadwal;
use App\Filament\Resources\Jadwals\Pages\AturJadwal;
use App\Filament\Resources\Jadwals\Pages\ListJadwals;
use App\Models\Jadwal;
use App\Models\TahunAjaran;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class JadwalResource extends Resource
{
    protected static ?string $model = Jadwal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = 'Kurikulum';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Jadwal Pelajaran';

    protected static ?string $pluralModelLabel = 'Jadwal Pelajaran';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tahun_ajaran_id')
                    ->label('Tahun Ajaran')
                    ->options(fn (): array => TahunAjaran::query()->orderByDesc('nama')->pluck('nama', 'id')->all())
                    ->default(fn (): ?int => TahunAjaran::query()->where('is_aktif', true)->value('id'))
                    ->required(),
                Select::make('semester')
                    ->options(Semester::class)
                    ->default(fn (): Semester => Semester::berdasarkanTanggal(now()))
                    ->required(),
                TextInput::make('nama')
                    ->label('Keterangan')
                    ->placeholder('Revisi 1')
                    ->helperText('Opsional, untuk membedakan beberapa draf jadwal di semester yang sama.')
                    ->maxLength(255),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('tahunAjaran')->withCount('isi'))
            ->defaultSort('updated_at', 'desc')
            ->recordUrl(fn (Jadwal $record): string => AturJadwal::getUrl(['record' => $record]))
            ->columns([
                TextColumn::make('judul')
                    ->label('Jadwal')
                    ->state(fn (Jadwal $record): string => $record->judul())
                    ->weight('bold'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('isi_count')
                    ->label('JP Terisi'),
                TextColumn::make('diterbitkan_pada')
                    ->label('Diterbitkan')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-'),
                TextColumn::make('updated_at')
                    ->label('Diubah')
                    ->since(),
            ])
            ->filters([
                SelectFilter::make('tahun_ajaran_id')
                    ->label('Tahun Ajaran')
                    ->options(fn (): array => TahunAjaran::query()->orderByDesc('nama')->pluck('nama', 'id')->all())
                    ->default(fn (): ?int => TahunAjaran::query()->where('is_aktif', true)->value('id')),
                SelectFilter::make('status')
                    ->options(StatusJadwal::class),
            ])
            ->recordActions([
                Action::make('atur')
                    ->label('Atur')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (Jadwal $record): string => AturJadwal::getUrl(['record' => $record])),
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Jadwal beserta seluruh isinya akan dihapus permanen.'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJadwals::route('/'),
            'atur' => AturJadwal::route('/{record}'),
        ];
    }
}
