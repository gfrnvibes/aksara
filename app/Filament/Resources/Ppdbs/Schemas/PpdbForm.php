<?php

namespace App\Filament\Resources\Ppdbs\Schemas;

use App\Enums\Agama;
use App\Enums\JenisKelamin;
use App\Filament\Resources\Ijazahs\IjazahResource;
use App\Models\Ppdb;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Services\Arsip\SimpanBerkasArsip;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class PpdbForm
{
    /**
     * Largest document an applicant may upload, in kilobytes, before images are compressed.
     */
    public const int UKURAN_MAKS_KB = 5120;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Data Calon Siswa')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('tahun_ajaran_id')
                            ->label('Tahun Ajaran')
                            ->relationship('tahunAjaran', 'nama', fn ($query) => $query->orderByDesc('nama'))
                            ->default(fn (): ?int => TahunAjaran::untukPpdb()?->id)
                            ->disabledOn('edit')
                            ->live()
                            ->required(),
                        TextInput::make('asal_sekolah')
                            ->label('Asal Sekolah (SD/MI)')
                            ->maxLength(255),
                        TextInput::make('nama_lengkap')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('nisn')
                            ->label('NISN')
                            ->required()
                            ->rules(['digits:10'])
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('tahun_ajaran_id', $get('tahun_ajaran_id'))),
                        TextInput::make('nik')
                            ->label('NIK')
                            ->required()
                            ->rules(['digits:16'])
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('tahun_ajaran_id', $get('tahun_ajaran_id'))),
                        TextInput::make('tempat_lahir')
                            ->label('Tempat Lahir')
                            ->required()
                            ->maxLength(255),
                        DatePicker::make('tanggal_lahir')
                            ->label('Tanggal Lahir')
                            ->required(),
                        Radio::make('jenis_kelamin')
                            ->label('Jenis Kelamin')
                            ->options(JenisKelamin::class)
                            ->inline()
                            ->required(),
                        Select::make('agama')
                            ->options(Agama::class)
                            ->default(Agama::Islam),
                    ]),
                Section::make('Alamat & Kontak')
                    ->schema([
                        Textarea::make('alamat')
                            ->required()
                            ->rows(3),
                        TextInput::make('nomor_telepon')
                            ->label('No. HP / WhatsApp')
                            ->tel()
                            ->required()
                            ->maxLength(30),
                        TextInput::make('nomor_kip_pip')
                            ->label('Nomor KIP/PIP')
                            ->maxLength(100),
                    ]),
                Section::make('Orang Tua / Wali')
                    ->schema([
                        TextInput::make('nama_ayah_kandung')
                            ->label('Nama Ayah Kandung')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('nama_ibu_kandung')
                            ->label('Nama Ibu Kandung')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('nama_wali')
                            ->label('Nama Wali')
                            ->helperText('Diisi bila calon siswa tinggal bersama wali.')
                            ->maxLength(255),
                    ]),
                Section::make('Berkas')
                    ->description('PDF atau gambar, masing-masing maksimal 5 MB. Gambar otomatis diperkecil.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])
                            ->statePath('berkas')
                            ->schema(array_map(
                                fn (string $kunci): FileUpload => static::unggahBerkas($kunci),
                                array_keys(Ppdb::BERKAS),
                            )),
                    ]),
                Textarea::make('catatan')
                    ->label('Catatan Panitia')
                    ->helperText('Misalnya kekurangan berkas atau alasan penolakan. Tidak ditampilkan kepada pendaftar.')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    protected static function unggahBerkas(string $kunci): FileUpload
    {
        $adalahFoto = $kunci === 'foto';

        return FileUpload::make($kunci)
            ->label(Ppdb::BERKAS[$kunci])
            ->disk(fn (): string => Ppdb::disk())
            ->visibility('private')
            ->acceptedFileTypes($adalahFoto ? ['image/jpeg', 'image/png', 'image/webp'] : IjazahResource::JENIS_BERKAS)
            ->maxSize(static::UKURAN_MAKS_KB)
            ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => app(SimpanBerkasArsip::class)
                ->simpan($file, 'ppdb', $adalahFoto ? Siswa::SISI_FOTO : SimpanBerkasArsip::SISI_TERPANJANG));
    }
}
