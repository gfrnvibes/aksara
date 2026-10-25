<?php

namespace App\Filament\Resources\Kelas;

use App\Filament\Resources\Kelas\Pages\ManageKelas;
use App\Models\Kelas;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class KelasResource extends Resource
{
    protected static ?string $model = Kelas::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Kelas';

    protected static ?string $pluralModelLabel = 'Kelas';

    protected static ?string $recordTitleAttribute = 'nama';

    /**
     * @return array<int, string>
     */
    public static function getTingkatOptions(): array
    {
        return [7 => 'VII', 8 => 'VIII', 9 => 'IX'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tingkat')
                    ->options(static::getTingkatOptions())
                    ->required(),
                TextInput::make('nama')
                    ->label('Nama Kelas')
                    ->placeholder('VII-A')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nama')
            ->columns([
                TextColumn::make('nama')
                    ->label('Nama Kelas')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tingkat')
                    ->formatStateUsing(fn (int $state): string => static::getTingkatOptions()[$state] ?? (string) $state)
                    ->sortable(),
                TextColumn::make('siswa_count')
                    ->label('Jumlah Siswa')
                    ->counts('siswa'),
            ])
            ->filters([
                SelectFilter::make('tingkat')
                    ->options(static::getTingkatOptions()),
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
            'index' => ManageKelas::route('/'),
        ];
    }
}
