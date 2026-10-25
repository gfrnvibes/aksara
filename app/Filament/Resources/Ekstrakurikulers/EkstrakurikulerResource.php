<?php

namespace App\Filament\Resources\Ekstrakurikulers;

use App\Filament\Resources\Ekstrakurikulers\Pages\ManageEkstrakurikulers;
use App\Models\Ekstrakurikuler;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
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
                Toggle::make('is_wajib')
                    ->label('Ekstrakurikuler wajib')
                    ->helperText('Ekstrakurikuler wajib otomatis ditambahkan ke rapor baru.'),
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
                IconColumn::make('is_wajib')
                    ->label('Wajib')
                    ->boolean(),
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
            'index' => ManageEkstrakurikulers::route('/'),
        ];
    }
}
