<?php

namespace App\Filament\Akreditasi\Resources\PelatihanGtks;

use App\Filament\Akreditasi\Concerns\AksesPanelAkreditasi;
use App\Filament\Akreditasi\Resources\PelatihanGtks\Pages\ManagePelatihanGtks;
use App\Models\EdmIndikator;
use App\Models\Gtk;
use App\Models\PelatihanGtk;
use App\Models\TahunAjaran;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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

class PelatihanGtkResource extends Resource
{
    use AksesPanelAkreditasi;

    protected static ?string $model = PelatihanGtk::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'Data Pendukung EDM';

    protected static ?string $navigationLabel = 'Diklat & KKG GTK (B1-B4, E2)';

    protected static ?string $modelLabel = 'Pelatihan GTK';

    protected static ?string $pluralModelLabel = 'Pengembangan Kompetensi GTK';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('tahun_ajaran_id')
                ->label('Tahun Ajaran')
                ->options(fn (): array => TahunAjaran::query()->orderByDesc('nama')->pluck('nama', 'id')->all())
                ->default(fn (): ?int => TahunAjaran::query()->where('is_aktif', true)->value('id'))
                ->required(),
            Select::make('gtk_id')
                ->label('Kepala / Guru / Tendik')
                ->options(fn (): array => Gtk::query()->where('is_aktif', true)->orderBy('nama')->pluck('nama', 'id')->all())
                ->searchable()
                ->required(),
            DatePicker::make('tanggal')->label('Tanggal Kegiatan')->default(today())->required(),
            Select::make('jenis')->label('Jenis Kegiatan')->options(PelatihanGtk::JENIS)->default('kkg_mgmp')->required(),
            TextInput::make('nama_kegiatan')->label('Nama Pelatihan / Workshop / KKG')->required()->maxLength(255)->columnSpanFull(),
            TextInput::make('penyelenggara')->label('Penyelenggara')->maxLength(150),
            Toggle::make('dapat_bantuan_biaya')->label('Mendapat Bantuan Biaya Madrasah (E2)')->default(false),
            FileUpload::make('berkas_path')
                ->label('Sertifikat / Surat Undangan (Opsional)')
                ->disk(fn (): string => EdmIndikator::diskName())
                ->directory('edm/pelatihan')
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
                TextColumn::make('gtk.nama')->label('GTK')->searchable()->sortable(),
                TextColumn::make('nama_kegiatan')->label('Kegiatan')->limit(35),
                TextColumn::make('jenis')
                    ->label('Jenis')
                    ->formatStateUsing(fn (string $state): string => PelatihanGtk::JENIS[$state] ?? $state),
                IconColumn::make('dapat_bantuan_biaya')->label('Bantuan Biaya')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePelatihanGtks::route('/'),
        ];
    }
}
