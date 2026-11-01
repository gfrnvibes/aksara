<?php

namespace App\Filament\Resources\KalenderAkademiks;

use App\Enums\JenisAgenda;
use App\Filament\Resources\KalenderAkademiks\Pages\ManageKalenderAkademiks;
use App\Models\KalenderAkademik;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class KalenderAkademikResource extends Resource
{
    protected static ?string $model = KalenderAkademik::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static string|UnitEnum|null $navigationGroup = 'Kurikulum';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Agenda';

    protected static ?string $pluralModelLabel = 'Kalender Akademik';

    protected static ?string $slug = 'kalender-akademik';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tahun_ajaran_id')
                    ->label('Tahun Ajaran')
                    ->options(fn (): array => TahunAjaran::query()->orderByDesc('nama')->pluck('nama', 'id')->all())
                    ->default(fn (): ?int => TahunAjaran::query()->where('is_aktif', true)->value('id'))
                    ->required(),
                TextInput::make('nama')
                    ->label('Nama Agenda')
                    ->placeholder('Penilaian Tengah Semester')
                    ->required()
                    ->maxLength(255),
                Select::make('jenis')
                    ->options(JenisAgenda::class)
                    ->required(),
                DatePicker::make('tanggal_mulai')
                    ->label('Mulai')
                    ->live()
                    ->afterStateUpdated(fn (?string $state, Get $get, Set $set) => blank($get('tanggal_selesai')) ? $set('tanggal_selesai', $state) : null)
                    ->required(),
                DatePicker::make('tanggal_selesai')
                    ->label('Selesai')
                    ->afterOrEqual('tanggal_mulai')
                    ->required(),
                Textarea::make('keterangan')
                    ->rows(2),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('tanggal_mulai')
            ->groups([
                Group::make('bulan')
                    ->label('Bulan')
                    ->getKeyFromRecordUsing(fn (KalenderAkademik $record): string => $record->tanggal_mulai->format('Y-m'))
                    ->getTitleFromRecordUsing(fn (KalenderAkademik $record): string => $record->tanggal_mulai->translatedFormat('F Y'))
                    ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderBy('tanggal_mulai', $direction === 'desc' ? 'desc' : 'asc'))
                    ->scopeQueryByKeyUsing(fn (Builder $query, string $key): Builder => $query->whereYear('tanggal_mulai', (int) substr($key, 0, 4))->whereMonth('tanggal_mulai', (int) substr($key, 5, 2)))
                    ->collapsible(),
            ])
            ->defaultGroup('bulan')
            ->columns([
                TextColumn::make('nama')
                    ->label('Agenda')
                    ->searchable()
                    ->description(fn (KalenderAkademik $record): ?string => $record->keterangan),
                TextColumn::make('jenis')
                    ->badge(),
                TextColumn::make('tanggal')
                    ->state(fn (KalenderAkademik $record): string => $record->rentang()),
                TextColumn::make('lama')
                    ->label('Lama')
                    ->state(fn (KalenderAkademik $record): string => ((int) $record->tanggal_mulai->diffInDays($record->tanggal_selesai) + 1).' hari'),
            ])
            ->filters([
                SelectFilter::make('tahun_ajaran_id')
                    ->label('Tahun Ajaran')
                    ->options(fn (): array => TahunAjaran::query()->orderByDesc('nama')->pluck('nama', 'id')->all())
                    ->default(fn (): ?int => TahunAjaran::query()->where('is_aktif', true)->value('id')),
                SelectFilter::make('jenis')
                    ->options(JenisAgenda::class),
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
            'index' => ManageKalenderAkademiks::route('/'),
        ];
    }
}
