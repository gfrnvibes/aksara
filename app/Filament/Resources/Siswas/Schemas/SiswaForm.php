<?php

namespace App\Filament\Resources\Siswas\Schemas;

use App\Enums\Agama;
use App\Enums\JenisKelamin;
use App\Enums\JenisOrangTua;
use App\Enums\StatusSiswa;
use App\Filament\Resources\Kelas\KelasResource;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class SiswaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Buku Induk')
                    ->persistTabInQueryString()
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Identitas Siswa')
                            ->icon('heroicon-o-identification')
                            ->schema(static::identitasSchema()),
                        Tab::make('Tempat Tinggal & Kesehatan')
                            ->icon('heroicon-o-home')
                            ->schema(static::tempatTinggalDanKesehatanSchema()),
                        Tab::make('Pendidikan')
                            ->icon('heroicon-o-academic-cap')
                            ->schema(static::pendidikanSchema()),
                        Tab::make('Orang Tua / Wali')
                            ->icon('heroicon-o-users')
                            ->schema([
                                static::orangTuaSection(JenisOrangTua::Ayah, 'E. Keterangan Tentang Ayah Kandung'),
                                static::orangTuaSection(JenisOrangTua::Ibu, 'F. Keterangan Tentang Ibu Kandung'),
                                static::orangTuaSection(JenisOrangTua::Wali, 'G. Keterangan Tentang Wali')
                                    ->description('Diisi hanya jika siswa memiliki wali selain orang tua kandung.')
                                    ->collapsed(),
                            ]),
                        Tab::make('Kegemaran & Perkembangan')
                            ->icon('heroicon-o-sparkles')
                            ->schema(static::kegemaranDanPerkembanganSchema()),
                        Tab::make('Setelah Lulus & Catatan')
                            ->icon('heroicon-o-clipboard-document-list')
                            ->schema(static::setelahLulusDanCatatanSchema()),
                    ]),
            ]);
    }

    /**
     * @return array<int, mixed>
     */
    protected static function identitasSchema(): array
    {
        return [
            Grid::make(['default' => 1, 'lg' => 4])
                ->schema([
                    FileUpload::make('foto')
                        ->label('Pas Foto 3x4')
                        ->image()
                        ->imageEditor()
                        ->imageAspectRatio('3:4')
                        ->disk('public')
                        ->directory('foto-siswa')
                        ->maxSize(2048),
                    Grid::make(2)
                        ->columnSpan(['lg' => 3])
                        ->schema([
                            TextInput::make('nis')
                                ->label('Nomor Induk Siswa')
                                ->required()
                                ->maxLength(30)
                                ->unique(ignoreRecord: true),
                            TextInput::make('nisn')
                                ->label('Nomor Induk Siswa Nasional')
                                ->rule('digits:10')
                                ->validationMessages(['digits' => 'NISN harus terdiri dari 10 digit angka.'])
                                ->unique(ignoreRecord: true),
                            Select::make('kelas_id')
                                ->label('Kelas Saat Ini')
                                ->relationship('kelas', 'nama')
                                ->searchable()
                                ->preload(),
                            Select::make('status')
                                ->options(StatusSiswa::class)
                                ->default(StatusSiswa::Aktif)
                                ->required(),
                        ]),
                ]),
            Section::make('A. Keterangan Tentang Diri Siswa')
                ->columns(2)
                ->schema([
                    TextInput::make('nama_lengkap')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('nama_panggilan')
                        ->maxLength(255),
                    Select::make('jenis_kelamin')
                        ->options(JenisKelamin::class)
                        ->required(),
                    Select::make('agama')
                        ->options(Agama::class)
                        ->default(Agama::Islam),
                    TextInput::make('tempat_lahir')
                        ->maxLength(255),
                    DatePicker::make('tanggal_lahir')
                        ->maxDate(now()),
                    TextInput::make('kewarganegaraan')
                        ->default('WNI')
                        ->maxLength(255),
                    TextInput::make('anak_ke')
                        ->label('Anak Keberapa')
                        ->numeric()
                        ->minValue(1),
                    Fieldset::make('Jumlah Saudara')
                        ->columns(3)
                        ->columnSpanFull()
                        ->schema([
                            TextInput::make('jumlah_saudara_kandung')
                                ->label('Kandung')
                                ->numeric()
                                ->minValue(0),
                            TextInput::make('jumlah_saudara_tiri')
                                ->label('Tiri')
                                ->numeric()
                                ->minValue(0),
                            TextInput::make('jumlah_saudara_angkat')
                                ->label('Angkat')
                                ->numeric()
                                ->minValue(0),
                        ]),
                    Select::make('status_yatim')
                        ->label('Anak Yatim / Piatu / Yatim Piatu')
                        ->options([
                            'Yatim' => 'Yatim',
                            'Piatu' => 'Piatu',
                            'Yatim Piatu' => 'Yatim Piatu',
                        ])
                        ->placeholder('Tidak'),
                    TextInput::make('bahasa_sehari_hari')
                        ->label('Bahasa Sehari-hari di Rumah')
                        ->maxLength(255),
                ]),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected static function tempatTinggalDanKesehatanSchema(): array
    {
        return [
            Section::make('B. Keterangan Tempat Tinggal')
                ->columns(2)
                ->schema([
                    Textarea::make('alamat')
                        ->rows(2)
                        ->columnSpanFull(),
                    TextInput::make('nomor_telepon')
                        ->tel()
                        ->maxLength(30),
                    Select::make('tinggal_dengan')
                        ->label('Tinggal Dengan')
                        ->options([
                            'Orang Tua' => 'Orang Tua',
                            'Saudara' => 'Saudara',
                            'Wali' => 'Wali',
                            'Asrama' => 'Asrama / Pondok',
                            'Kost' => 'Kost',
                        ]),
                    TextInput::make('jarak_ke_sekolah')
                        ->label('Jarak Tempat Tinggal ke Sekolah')
                        ->placeholder('contoh: 2 km'),
                ]),
            Section::make('C. Keterangan Kesehatan')
                ->columns(2)
                ->schema([
                    Select::make('golongan_darah')
                        ->options(['A' => 'A', 'B' => 'B', 'AB' => 'AB', 'O' => 'O']),
                    TextInput::make('riwayat_penyakit')
                        ->label('Penyakit yang Pernah Diderita')
                        ->placeholder('TBC / Cacar / Malaria / dll.'),
                    TextInput::make('kelainan_jasmani')
                        ->maxLength(255),
                    Grid::make(2)
                        ->schema([
                            TextInput::make('tinggi_badan')
                                ->numeric()
                                ->suffix('cm'),
                            TextInput::make('berat_badan')
                                ->numeric()
                                ->suffix('kg'),
                        ]),
                ]),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected static function pendidikanSchema(): array
    {
        return [
            Section::make('D. Keterangan Pendidikan')
                ->schema([
                    Fieldset::make('Pendidikan Sebelumnya')
                        ->columns(2)
                        ->schema([
                            TextInput::make('lulusan_dari')
                                ->placeholder('Nama SD / MI asal'),
                            TextInput::make('lama_belajar')
                                ->placeholder('contoh: 6 tahun'),
                            DatePicker::make('tanggal_sttb')
                                ->label('Tanggal STTB / Ijazah'),
                            TextInput::make('nomor_sttb')
                                ->label('Nomor STTB / Ijazah'),
                        ]),
                    Fieldset::make('Pindahan')
                        ->columns(2)
                        ->schema([
                            TextInput::make('pindahan_dari')
                                ->label('Dari Sekolah'),
                            TextInput::make('alasan_pindah')
                                ->label('Alasan'),
                        ]),
                    Fieldset::make('Diterima di Sekolah Ini')
                        ->columns(2)
                        ->schema([
                            Select::make('diterima_di_tingkat')
                                ->label('Di Tingkat')
                                ->options(KelasResource::getTingkatOptions()),
                            DatePicker::make('tanggal_diterima')
                                ->label('Tanggal'),
                        ]),
                ]),
        ];
    }

    protected static function orangTuaSection(JenisOrangTua $jenis, string $heading): Section
    {
        $relationship = $jenis->value;

        return Section::make($heading)
            ->relationship(
                $relationship,
                condition: fn (Get $get): bool => filled($get("{$relationship}.nama")),
            )
            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => [...$data, 'jenis' => $jenis])
            ->collapsible()
            ->columns(2)
            ->schema([
                TextInput::make('nama')
                    ->maxLength(255)
                    ->helperText($jenis === JenisOrangTua::Wali ? 'Kosongkan jika tidak ada wali.' : null),
                TextInput::make('tempat_lahir')
                    ->maxLength(255),
                DatePicker::make('tanggal_lahir'),
                Select::make('agama')
                    ->options(Agama::class),
                TextInput::make('kewarganegaraan')
                    ->default('WNI'),
                TextInput::make('pendidikan')
                    ->placeholder('SD / SMP / SMA / S1 / ...'),
                TextInput::make('pekerjaan'),
                TextInput::make('penghasilan')
                    ->label('Penghasilan per Bulan'),
                Textarea::make('alamat')
                    ->label('Alamat Rumah')
                    ->rows(2),
                TextInput::make('nomor_telepon')
                    ->tel(),
                Toggle::make('masih_hidup')
                    ->label('Masih hidup')
                    ->default(true)
                    ->hidden($jenis === JenisOrangTua::Wali),
            ]);
    }

    /**
     * @return array<int, mixed>
     */
    protected static function kegemaranDanPerkembanganSchema(): array
    {
        return [
            Section::make('H. Kegemaran Siswa')
                ->columns(2)
                ->schema([
                    TextInput::make('kegemaran_kesenian')
                        ->label('Kesenian'),
                    TextInput::make('kegemaran_olahraga')
                        ->label('Olah Raga'),
                    TextInput::make('kegemaran_organisasi')
                        ->label('Kemasyarakatan / Organisasi'),
                    TextInput::make('kegemaran_lain')
                        ->label('Lain-lain'),
                ]),
            Section::make('I. Keterangan Perkembangan Siswa')
                ->schema([
                    Repeater::make('beasiswa')
                        ->label('Menerima Bea Siswa')
                        ->table([
                            Repeater\TableColumn::make('Tahun'),
                            Repeater\TableColumn::make('Kelas / Tingkat'),
                            Repeater\TableColumn::make('Dari'),
                        ])
                        ->schema([
                            TextInput::make('tahun'),
                            TextInput::make('kelas'),
                            TextInput::make('dari'),
                        ])
                        ->defaultItems(0)
                        ->addActionLabel('Tambah bea siswa'),
                    Fieldset::make('Meninggalkan Sekolah')
                        ->columns(2)
                        ->schema([
                            DatePicker::make('tanggal_meninggalkan_sekolah')
                                ->label('Tanggal'),
                            TextInput::make('alasan_meninggalkan_sekolah')
                                ->label('Alasan'),
                        ]),
                    Fieldset::make('Akhir Pendidikan')
                        ->columns(2)
                        ->schema([
                            DatePicker::make('tanggal_tamat')
                                ->label('Tamat Belajar'),
                            TextInput::make('nomor_sttb_tamat')
                                ->label('Nomor STTB / Ijazah'),
                        ]),
                ]),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected static function setelahLulusDanCatatanSchema(): array
    {
        return [
            Section::make('J. Keterangan Setelah Selesai Pendidikan')
                ->columns(2)
                ->schema([
                    TextInput::make('melanjutkan_di')
                        ->label('Melanjutkan di')
                        ->columnSpanFull(),
                    DatePicker::make('tanggal_mulai_bekerja'),
                    TextInput::make('nama_tempat_bekerja')
                        ->label('Nama Perusahaan / Lembaga'),
                    TextInput::make('penghasilan_bekerja')
                        ->label('Penghasilan'),
                ]),
            Section::make('K. Catatan Prestasi')
                ->schema([
                    Repeater::make('prestasi')
                        ->hiddenLabel()
                        ->simple(
                            TextInput::make('prestasi')
                                ->placeholder('Prestasi yang pernah dicapai'),
                        )
                        ->defaultItems(0)
                        ->addActionLabel('Tambah prestasi'),
                ]),
            Section::make('L. Lain-lain')
                ->schema([
                    Textarea::make('catatan_penting')
                        ->label('Catatan yang Penting')
                        ->rows(4),
                ]),
        ];
    }
}
