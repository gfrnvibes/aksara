<?php

namespace App\Filament\Akreditasi\Resources\InventarisMedias;

use App\Filament\Akreditasi\Concerns\AksesPanelAkreditasi;
use App\Filament\Akreditasi\Resources\InventarisMedias\Pages\ManageInventarisMedias;
use App\Models\EdmIndikator;
use App\Models\InventarisMedia;
use App\Models\MataPelajaran;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class InventarisMediaResource extends Resource
{
    use AksesPanelAkreditasi;

    protected static ?string $model = InventarisMedia::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = 'Data Pendukung EDM';

    protected static ?string $navigationLabel = 'Inventaris Media & Buku (D2-D5)';

    protected static ?string $modelLabel = 'Inventaris Media & Buku';

    protected static ?string $pluralModelLabel = 'Inventaris Media & Buku Belajar';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nama')->label('Nama Alat Peraga / Media / Buku')->required()->maxLength(255),
            Select::make('kategori')->label('Kategori')->options(InventarisMedia::KATEGORI)->default('non_digital')->required(),
            Select::make('mata_pelajaran_id')
                ->label('Mata Pelajaran (Opsional)')
                ->options(fn (): array => MataPelajaran::query()->orderBy('nama')->pluck('nama', 'id')->all())
                ->searchable(),
            TextInput::make('jumlah')->label('Jumlah Unit')->numeric()->minValue(1)->default(1)->required(),
            Toggle::make('kondisi_baik')->label('Kondisi Baik & Layak Pakai')->default(true),
            Toggle::make('digunakan_siswa')->label('Digunakan Siswa (D5)')->default(true),
            TextInput::make('keterangan')->label('Keterangan Tambahan')->maxLength(255)->columnSpanFull(),
            FileUpload::make('berkas_path')
                ->label('Foto Alat Peraga / Screenshot Media TIK (Opsional)')
                ->disk(fn (): string => EdmIndikator::diskName())
                ->directory('edm/inventaris')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                ->maxSize(5120)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')->label('Nama Media / Buku')->searchable(),
                TextColumn::make('kategori')
                    ->label('Kategori')
                    ->formatStateUsing(fn (string $state): string => InventarisMedia::KATEGORI[$state] ?? $state),
                TextColumn::make('mataPelajaran.nama')->label('Mapel')->placeholder('Umum'),
                TextColumn::make('jumlah')->label('Jumlah'),
                IconColumn::make('kondisi_baik')->label('Baik')->boolean(),
                IconColumn::make('digunakan_siswa')->label('Dipakai Siswa')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageInventarisMedias::route('/'),
        ];
    }
}
