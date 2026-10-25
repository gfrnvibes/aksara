<?php

namespace App\Filament\Resources\TahunAjarans;

use App\Filament\Resources\TahunAjarans\Pages\ManageTahunAjarans;
use App\Models\TahunAjaran;
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

class TahunAjaranResource extends Resource
{
    protected static ?string $model = TahunAjaran::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Tahun Ajaran';

    protected static ?string $pluralModelLabel = 'Tahun Ajaran';

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama')
                    ->label('Tahun Ajaran')
                    ->placeholder('2025/2026')
                    ->required()
                    ->regex('/^\d{4}\/\d{4}$/')
                    ->validationMessages(['regex' => 'Format tahun ajaran harus seperti 2025/2026.'])
                    ->unique(ignoreRecord: true),
                Toggle::make('is_aktif')
                    ->label('Tahun ajaran aktif')
                    ->helperText('Hanya satu tahun ajaran yang dapat aktif.'),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nama', 'desc')
            ->columns([
                TextColumn::make('nama')
                    ->label('Tahun Ajaran')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_aktif')
                    ->label('Aktif')
                    ->boolean(),
                TextColumn::make('rapor_count')
                    ->label('Jumlah Rapor')
                    ->counts('rapor'),
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
            'index' => ManageTahunAjarans::route('/'),
        ];
    }
}
