<?php

namespace App\Filament\Akreditasi\Resources\RapatMadrasahs;

use App\Filament\Akreditasi\Concerns\AksesPanelAkreditasi;
use App\Filament\Akreditasi\Resources\RapatMadrasahs\Pages\ManageRapatMadrasahs;
use App\Models\EdmIndikator;
use App\Models\RapatMadrasah;
use App\Models\TahunAjaran;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class RapatMadrasahResource extends Resource
{
    use AksesPanelAkreditasi;

    protected static ?string $model = RapatMadrasah::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Data Pendukung EDM';

    protected static ?string $navigationLabel = 'Rapat & Notulensi (A7, E1)';

    protected static ?string $modelLabel = 'Rapat Madrasah';

    protected static ?string $pluralModelLabel = 'Rapat & Notulensi Madrasah';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('tahun_ajaran_id')
                ->label('Tahun Ajaran')
                ->options(fn (): array => TahunAjaran::query()->orderByDesc('nama')->pluck('nama', 'id')->all())
                ->default(fn (): ?int => TahunAjaran::query()->where('is_aktif', true)->value('id'))
                ->required(),
            DatePicker::make('tanggal')->label('Tanggal Rapat')->default(today())->required(),
            TextInput::make('judul')->label('Agenda / Judul Rapat')->required()->maxLength(255)->columnSpanFull(),
            Select::make('jenis')->label('Jenis Pertemuan')->options(RapatMadrasah::JENIS)->default('evaluasi_diri')->required(),
            Toggle::make('melibatkan_guru')->label('Melibatkan Guru')->default(true),
            Toggle::make('melibatkan_tendik')->label('Melibatkan Tendik')->default(true),
            Toggle::make('melibatkan_komite')->label('Melibatkan Komite / Orang Tua')->default(false),
            Toggle::make('melibatkan_yayasan')->label('Melibatkan Yayasan')->default(false),
            Textarea::make('notulensi')->label('Ringkasan Notulensi Rapat')->rows(3)->columnSpanFull(),
            FileUpload::make('berkas_path')
                ->label('Foto Rapat / Scan Daftar Hadir (Opsional)')
                ->disk(fn (): string => EdmIndikator::diskName())
                ->directory('edm/rapat')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                ->maxSize(5120)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('tanggal', 'desc')
            ->columns([
                TextColumn::make('tanggal')->label('Tanggal')->date('d/m/Y')->sortable(),
                TextColumn::make('judul')->label('Judul Rapat')->searchable(),
                TextColumn::make('jenis')
                    ->label('Jenis')
                    ->formatStateUsing(fn (string $state): string => RapatMadrasah::JENIS[$state] ?? $state),
                IconColumn::make('melibatkan_komite')->label('Komite')->boolean(),
                IconColumn::make('melibatkan_yayasan')->label('Yayasan')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRapatMadrasahs::route('/'),
        ];
    }
}
