<?php

namespace App\Filament\Akreditasi\Resources\SupervisiPembelajarans;

use App\Filament\Akreditasi\Concerns\AksesPanelAkreditasi;
use App\Filament\Akreditasi\Resources\SupervisiPembelajarans\Pages\ManageSupervisiPembelajarans;
use App\Models\EdmIndikator;
use App\Models\Gtk;
use App\Models\SupervisiPembelajaran;
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

class SupervisiPembelajaranResource extends Resource
{
    use AksesPanelAkreditasi;

    protected static ?string $model = SupervisiPembelajaran::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEye;

    protected static string|UnitEnum|null $navigationGroup = 'Data Pendukung EDM';

    protected static ?string $navigationLabel = 'Supervisi Guru (A3, C4)';

    protected static ?string $modelLabel = 'Supervisi Pembelajaran';

    protected static ?string $pluralModelLabel = 'Supervisi Pembelajaran';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('tahun_ajaran_id')
                ->label('Tahun Ajaran')
                ->options(fn (): array => TahunAjaran::query()->orderByDesc('nama')->pluck('nama', 'id')->all())
                ->default(fn (): ?int => TahunAjaran::query()->where('is_aktif', true)->value('id'))
                ->required(),
            Select::make('gtk_id')
                ->label('Guru yang Disupervisi')
                ->options(fn (): array => Gtk::query()->where('is_aktif', true)->where('jenis_gtk', 'guru')->orderBy('nama')->pluck('nama', 'id')->all())
                ->searchable()
                ->required(),
            DatePicker::make('tanggal')->label('Tanggal Supervisi')->default(today())->required(),
            TextInput::make('nomor_sk')->label('Nomor SK Tim Supervisi')->maxLength(120),
            TextInput::make('supervisor')->label('Nama Supervisor (Kepala / Guru Senior)')->maxLength(150),
            Toggle::make('penilaian_otentik')->label('Melakukan Penilaian Otentik (C4)')->default(true),
            Textarea::make('catatan')->label('Catatan Hasil Supervisi')->rows(2)->columnSpanFull(),
            Textarea::make('tindak_lanjut')->label('Tindak Lanjut Supervisi')->rows(2)->columnSpanFull(),
            FileUpload::make('berkas_path')
                ->label('SK / Instrumen / Foto Supervisi (Opsional)')
                ->disk(fn (): string => EdmIndikator::diskName())
                ->directory('edm/supervisi')
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
                TextColumn::make('gtk.nama')->label('Guru')->searchable()->sortable(),
                TextColumn::make('nomor_sk')->label('No. SK')->placeholder('-'),
                IconColumn::make('penilaian_otentik')->label('Penilaian Otentik')->boolean(),
                TextColumn::make('tindak_lanjut')->label('Tindak Lanjut')->limit(35)->placeholder('-'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSupervisiPembelajarans::route('/'),
        ];
    }
}
