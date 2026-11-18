<?php

namespace App\Filament\Resources\Ekstrakurikulers;

use App\Filament\Actions\HapusMasterDataActions;
use App\Filament\Resources\Ekstrakurikulers\Pages\ManageEkstrakurikulers;
use App\Models\Ekstrakurikuler;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class EkstrakurikulerResource extends Resource
{
    protected static ?string $model = Ekstrakurikuler::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Ekstrakurikuler';

    protected static ?string $pluralModelLabel = 'Ekstrakurikuler';

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama')
                    ->label('Nama Ekstrakurikuler')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Select::make('pembina_id')
                    ->label('Pembina')
                    ->relationship('pembina', 'nama', fn (Builder $query, ?Ekstrakurikuler $record): Builder => $query
                        ->where(fn (Builder $gtk) => $gtk->where('is_aktif', true)->when($record?->pembina_id, fn (Builder $gtk, int $id) => $gtk->orWhereKey($id)))
                        ->orderBy('nama'))
                    ->searchable()
                    ->preload()
                    ->helperText('GTK yang ditunjuk otomatis dapat mengelola anggota, kegiatan, dan nilai ekstrakurikuler ini di Panel Guru.'),
                Toggle::make('is_wajib')
                    ->label('Ekstrakurikuler wajib')
                    ->helperText('Ekstrakurikuler wajib otomatis ditambahkan ke rapor baru.'),
                Toggle::make('is_aktif')
                    ->label('Aktif')
                    ->helperText('Ekstrakurikuler nonaktif tidak ditawarkan untuk rapor baru, tetapi tetap tampil di rapor lama.')
                    ->default(true),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nama')
            ->columns([
                TextColumn::make('nama')
                    ->label('Ekstrakurikuler')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('pembina.nama')
                    ->label('Pembina')
                    ->placeholder('Belum ditunjuk')
                    ->searchable(),
                IconColumn::make('is_wajib')
                    ->label('Wajib')
                    ->boolean(),
                ToggleColumn::make('is_aktif')
                    ->label('Aktif')
                    ->disabled(fn (Ekstrakurikuler $record): bool => ! (auth()->user()?->can('update', $record) ?? false)),
            ])
            ->filters([
                TernaryFilter::make('is_aktif')
                    ->label('Status')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif'),
            ])
            ->recordActions([
                EditAction::make(),
                HapusMasterDataActions::hapus(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    HapusMasterDataActions::hapusMassal(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEkstrakurikulers::route('/'),
        ];
    }
}
