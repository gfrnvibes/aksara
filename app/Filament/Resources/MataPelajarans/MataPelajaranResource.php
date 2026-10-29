<?php

namespace App\Filament\Resources\MataPelajarans;

use App\Enums\KelompokMapel;
use App\Filament\Actions\HapusMasterDataActions;
use App\Filament\Resources\MataPelajarans\Pages\ManageMataPelajarans;
use App\Models\MataPelajaran;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class MataPelajaranResource extends Resource
{
    protected static ?string $model = MataPelajaran::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Mata Pelajaran';

    protected static ?string $pluralModelLabel = 'Mata Pelajaran';

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama')
                    ->label('Nama Mata Pelajaran')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->columnSpanFull(),
                TextInput::make('singkatan')
                    ->label('Singkatan')
                    ->maxLength(20)
                    ->nullable()
                    ->helperText('Opsional; digunakan sebagai singkatan mata pelajaran.'),
                Select::make('kelompok')
                    ->options(KelompokMapel::class)
                    ->required(),
                TextInput::make('kkm')
                    ->label('KKM')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(100)
                    ->default(75)
                    ->required(),
                TextInput::make('urutan')
                    ->label('Urutan di Rapor')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required(),
                Toggle::make('is_aktif')
                    ->label('Aktif')
                    ->default(true)
                    ->inline(false),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('kelompok')->orderBy('urutan'))
            ->columns([
                TextColumn::make('kelompok')
                    ->badge()
                    ->sortable(),
                TextColumn::make('urutan')
                    ->sortable(),
                TextColumn::make('singkatan')
                    ->label('Singkatan')
                    ->sortable()
                    ->toggleable()
                    ->searchable()
                    ->default('-')
                    ->placeholder('-'),
                TextColumn::make('nama')
                    ->label('Mata Pelajaran')
                    ->searchable(),
                TextColumn::make('kkm')
                    ->label('KKM'),
                IconColumn::make('is_aktif')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('kelompok')
                    ->options(KelompokMapel::class),
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
            'index' => ManageMataPelajarans::route('/'),
        ];
    }
}
