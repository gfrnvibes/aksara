<?php

namespace App\Filament\Resources\KopSurats;

use App\Filament\Resources\KopSurats\Pages\CreateKopSurat;
use App\Filament\Resources\KopSurats\Pages\EditKopSurat;
use App\Filament\Resources\KopSurats\Pages\ListKopSurats;
use App\Models\KopSurat;
use App\Services\Surat\PengaturanKop;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class KopSuratResource extends Resource
{
    protected static ?string $model = KopSurat::class;

    protected static ?string $slug = 'kop-surat';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $modelLabel = 'Kop Surat';

    protected static ?string $pluralModelLabel = 'Kop Surat';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        $default = PengaturanKop::bawaan();

        return $schema->columns(1)->components([
            TextInput::make('nama')->label('Nama template')->placeholder('Kop resmi madrasah')->required()->maxLength(255),
            Group::make()->statePath('konfigurasi')->schema([
                Tabs::make('Pengaturan kop')->tabs([
                    Tab::make('Identitas')->icon(Heroicon::OutlinedBuildingOffice2)->schema([
                        Select::make('mode')->options(['terstruktur' => 'Kop terstruktur (teks & logo)', 'gambar' => 'Kop gambar'])->default('terstruktur')->required()->live(),
                        Section::make('Identitas madrasah')->description('Dipakai pada mode terstruktur. Baris yang dikosongkan tidak dicetak.')->columns(2)->schema([
                            TextInput::make('instansi')->label('Yayasan / instansi')->default($default['instansi'])->maxLength(500)->live(onBlur: true)->columnSpanFull(),
                            TextInput::make('nama')->label('Nama madrasah')->default($default['nama'])->required()->maxLength(255)->live(onBlur: true)->columnSpanFull(),
                            Textarea::make('alamat')->default($default['alamat'])->maxLength(500)->rows(2)->live(onBlur: true)->columnSpanFull(),
                            TextInput::make('wilayah')->default($default['wilayah'])->maxLength(500)->live(onBlur: true),
                            TextInput::make('telepon')->default($default['telepon'])->maxLength(500)->live(onBlur: true),
                            TextInput::make('email')->default($default['email'])->maxLength(500)->live(onBlur: true),
                            TextInput::make('website')->maxLength(500)->live(onBlur: true),
                            TextInput::make('npsn')->label('NPSN')->default($default['npsn'])->maxLength(500)->live(onBlur: true),
                            TextInput::make('nsm')->label('NSM')->maxLength(500)->live(onBlur: true),
                        ]),
                    ]),
                    Tab::make('Logo & Gambar')->icon(Heroicon::OutlinedPhoto)->columns(2)->schema([
                        static::unggah('logo_kiri', 'Logo kiri'), static::unggah('logo_kanan', 'Logo kanan'),
                        Toggle::make('tampilkan_logo_kiri')->default(true)->live(), Toggle::make('tampilkan_logo_kanan')->default(true)->live(),
                        static::angka('logo_kiri_mm', 'Batas ukuran logo kiri', 22, 12, 35)->suffix('mm'),
                        static::angka('logo_kanan_mm', 'Batas ukuran logo kanan', 22, 12, 35)->suffix('mm'),
                        static::unggah('gambar', 'Gambar kop lengkap')->helperText('Untuk mode kop gambar. PNG/JPG maksimal 2 MB, rasio gambar tetap dipertahankan.')->columnSpanFull(),
                        static::angka('tinggi_gambar_mm', 'Tinggi maksimum gambar kop', 35, 15, 60)->suffix('mm'),
                    ]),
                    Tab::make('Tata Letak')->icon(Heroicon::OutlinedAdjustmentsHorizontal)->columns(2)->schema([
                        static::angka('ukuran_huruf', 'Ukuran teks', 10, 8, 16)->suffix('pt'),
                        static::angka('ukuran_nama', 'Ukuran nama madrasah', 16, 12, 28)->suffix('pt'),
                        Select::make('perataan')->options(['left' => 'Kiri', 'center' => 'Tengah', 'right' => 'Kanan'])->default('center')->required()->live(),
                        Select::make('garis')->label('Garis pembatas')->options(['none' => 'Tanpa garis', 'solid' => 'Garis tunggal', 'double' => 'Garis ganda'])->default('double')->required()->live(),
                        static::angka('jarak_baris', 'Jarak antarbaris', 1.3, 1, 2)->step(0.1),
                        static::angka('jarak_bawah_mm', 'Jarak setelah kop', 4, 2, 12)->suffix('mm'),
                    ]),
                ]),
            ]),
            Section::make('Pratinjau A4')->description('Pratinjau mengikuti isian. Simpan Draf untuk menyimpan template; Jadikan Default hanya menerapkan draf yang sudah tersimpan.')->schema([
                View::make('filament.kop-surat.pratinjau')->viewData(fn (Get $get): array => ['kop' => [...PengaturanKop::bawaan(), ...(array) $get('konfigurasi')]]),
            ]),
        ]);
    }

    protected static function unggah(string $name, string $label): FileUpload
    {
        return FileUpload::make($name)->label($label)->disk('public')->directory('kop-surat')->visibility('public')
            ->acceptedFileTypes(['image/png', 'image/jpeg'])->maxSize(2048)->rules(['nullable', 'image'])->live()
            ->helperText('PNG/JPG maksimal 2 MB, bebas rasio dan dimensi. Tampilan otomatis disesuaikan secara proporsional di kop tanpa dipotong.');
    }

    protected static function angka(string $name, string $label, float $default, float $min, float $max): TextInput
    {
        return TextInput::make($name)->label($label)->numeric()->default($default)->minValue($min)->maxValue($max)->required()->live(onBlur: true);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('updated_at', 'desc')->defaultPaginationPageOption(25)->columns([
            TextColumn::make('nama')->searchable()->weight('bold'),
            TextColumn::make('konfigurasi.mode')->label('Mode')->badge(),
            TextColumn::make('default_untuk')->label('Default untuk')->state(fn (KopSurat $record): string => DB::table('default_kop_surats')->where('kop_surat_id', $record->id)->pluck('jenis')->map(fn ($jenis): string => PengaturanKop::JENIS[$jenis])->implode(', ') ?: 'Belum diterapkan'),
            TextColumn::make('updated_at')->label('Draf terakhir diubah')->dateTime('d M Y H:i'),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListKopSurats::route('/'), 'create' => CreateKopSurat::route('/create'), 'edit' => EditKopSurat::route('/{record}/edit')];
    }
}
