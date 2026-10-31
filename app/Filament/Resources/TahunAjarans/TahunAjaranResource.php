<?php

namespace App\Filament\Resources\TahunAjarans;

use App\Enums\Kurikulum;
use App\Filament\Actions\HapusMasterDataActions;
use App\Filament\Resources\TahunAjarans\Pages\ManageTahunAjarans;
use App\Models\TahunAjaran;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Text;
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
                Select::make('kurikulum')
                    ->options(Kurikulum::class)
                    ->default(Kurikulum::bawaan())
                    ->helperText('Menentukan format penilaian dan cetak rapor tahun ajaran ini. Tahun ajaran lama tetap memakai kurikulumnya sendiri.')
                    ->selectablePlaceholder(false)
                    ->required(),
                Toggle::make('is_aktif')
                    ->label('Tahun ajaran aktif')
                    ->helperText('Hanya satu tahun ajaran yang dapat aktif.'),
                TextInput::make('kepala_madrasah')
                    ->label('Kepala Madrasah')
                    ->helperText('Penanda tangan rapor tahun ajaran ini, beserta gelarnya.')
                    ->maxLength(255),
                TextInput::make('nip_kepala_madrasah')
                    ->label('NIP Kepala Madrasah')
                    ->maxLength(30),
                Fieldset::make('Periode Pendaftaran PPDB')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('ppdb_dibuka')
                            ->label('Dibuka'),
                        DatePicker::make('ppdb_ditutup')
                            ->label('Ditutup')
                            ->afterOrEqual('ppdb_dibuka')
                            ->helperText('Kosongkan bila belum ditentukan.'),
                        Text::make('Formulir PPDB di halaman publik hanya menerima pendaftaran pada rentang tanggal ini, untuk calon siswa tahun ajaran ini.')
                            ->color('gray')
                            ->columnSpanFull(),
                    ]),
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
                TextColumn::make('kurikulum')
                    ->badge(),
                IconColumn::make('is_aktif')
                    ->label('Aktif')
                    ->boolean(),
                TextColumn::make('kepala_madrasah')
                    ->label('Kepala Madrasah')
                    ->placeholder('Belum diisi'),
                TextColumn::make('ppdb_dibuka')
                    ->label('Periode PPDB')
                    ->formatStateUsing(fn (TahunAjaran $record): string => $record->ppdb_dibuka->format('d-m-Y').' s.d. '.($record->ppdb_ditutup?->format('d-m-Y') ?? 'belum ditentukan'))
                    ->placeholder('-'),
                TextColumn::make('rapor_count')
                    ->label('Jumlah Rapor')
                    ->counts('rapor'),
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
            'index' => ManageTahunAjarans::route('/'),
        ];
    }
}
