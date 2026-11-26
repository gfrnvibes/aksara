<?php

namespace App\Filament\Akreditasi\Resources\ProgramRkams;

use App\Filament\Akreditasi\Concerns\AksesPanelAkreditasi;
use App\Filament\Akreditasi\Resources\ProgramRkams\Pages\ManageProgramRkams;
use App\Models\EdmIndikator;
use App\Models\ProgramRkam;
use App\Models\TahunAjaran;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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

class ProgramRkamResource extends Resource
{
    use AksesPanelAkreditasi;

    protected static ?string $model = ProgramRkam::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'Data Pendukung EDM';

    protected static ?string $navigationLabel = 'Program Mutu & e-RKAM (B3, E1)';

    protected static ?string $modelLabel = 'Program Mutu & e-RKAM';

    protected static ?string $pluralModelLabel = 'Program Mutu & e-RKAM';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('tahun_ajaran_id')
                ->label('Tahun Ajaran')
                ->options(fn (): array => TahunAjaran::query()->orderByDesc('nama')->pluck('nama', 'id')->all())
                ->default(fn (): ?int => TahunAjaran::query()->where('is_aktif', true)->value('id'))
                ->required(),
            TextInput::make('jumlah_guru_sasaran')
                ->label('Jumlah Guru Sasaran Program Mutu (B3)')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required(),
            TextInput::make('nama_program')
                ->label('Nama Program Peningkatan Mutu / RKAM')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            Toggle::make('pakai_edm_rkam')->label('Disusun Menggunakan Aplikasi EDM & e-RKAM (E1)')->default(true),
            Toggle::make('disahkan_kepala')->label('Sudah Disahkan Kepala Madrasah (E1)')->default(true),
            Textarea::make('keterangan')->label('Keterangan')->rows(2)->columnSpanFull(),
            FileUpload::make('berkas_path')
                ->label('Screenshot Aplikasi EDM / e-RKAM / Dokumen (Opsional)')
                ->disk(fn (): string => EdmIndikator::diskName())
                ->directory('edm/rkam')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                ->maxSize(5120)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tahunAjaran.nama')->label('Tahun Ajaran')->sortable(),
                TextColumn::make('nama_program')->label('Program Mutu / RKAM')->searchable(),
                TextColumn::make('jumlah_guru_sasaran')->label('Guru Sasaran'),
                IconColumn::make('pakai_edm_rkam')->label('EDM & e-RKAM')->boolean(),
                IconColumn::make('disahkan_kepala')->label('Disahkan')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProgramRkams::route('/'),
        ];
    }
}
