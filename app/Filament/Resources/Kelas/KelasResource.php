<?php

namespace App\Filament\Resources\Kelas;

use App\Filament\Actions\HapusMasterDataActions;
use App\Filament\Resources\Kelas\Pages\ManageKelas;
use App\Models\Kelas;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
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
                TextInput::make('wali_kelas')
                    ->label('Wali Kelas')
                    ->helperText('Disalin ke rapor baru sebagai penanda tangan; rapor lama tidak ikut berubah.')
                    ->maxLength(255),
                TextInput::make('nip_wali_kelas')
                    ->label('NIP Wali Kelas')
                    ->maxLength(30),
                Toggle::make('is_aktif')
                    ->label('Aktif')
                    ->helperText('Kelas nonaktif tidak ditawarkan untuk siswa dan rapor baru, tetapi tetap tampil di rapor lama.')
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
                    ->label('Nama Kelas')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tingkat')
                    ->formatStateUsing(fn (int $state): string => static::getTingkatOptions()[$state] ?? (string) $state)
                    ->sortable(),
                TextColumn::make('wali_kelas')
                    ->label('Wali Kelas')
                    ->placeholder('Belum diisi'),
                TextColumn::make('siswa_count')
                    ->label('Jumlah Siswa')
                    ->counts('siswa'),
                ToggleColumn::make('is_aktif')
                    ->label('Aktif')
                    ->disabled(fn (Kelas $record): bool => ! (auth()->user()?->can('update', $record) ?? false)),
            ])
            ->filters([
                SelectFilter::make('tingkat')
                    ->options(static::getTingkatOptions()),
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
            'index' => ManageKelas::route('/'),
        ];
    }
}
