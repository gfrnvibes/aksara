<?php

namespace App\Filament\Imports;

use App\Enums\Agama;
use App\Enums\JenisKelamin;
use App\Models\Ppdb;
use App\Models\TahunAjaran;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;
use Illuminate\Validation\Rule;

class PpdbImporter extends Importer
{
    protected static ?string $model = Ppdb::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('nama_lengkap')
                ->label('Nama Lengkap')
                ->requiredMapping()
                ->castStateUsing(fn (mixed $state): ?string => trim((string) $state) === '' ? null : trim((string) $state))
                ->rules(['required', 'max:255'])
                ->example('Ahmad Fauzi'),
            ImportColumn::make('nisn')
                ->label('NISN')
                ->requiredMapping()
                ->castStateUsing(fn (mixed $state): ?string => trim((string) $state) === '' ? null : trim((string) $state))
                ->rules(['required', 'digits:10'])
                ->example('0123456789'),
            ImportColumn::make('nik')
                ->label('NIK')
                ->requiredMapping()
                ->castStateUsing(fn (mixed $state): ?string => trim((string) $state) === '' ? null : trim((string) $state))
                ->rules(['required', 'digits:16'])
                ->example('3201010101010001'),
            ImportColumn::make('tempat_lahir')
                ->label('Tempat Lahir')
                ->requiredMapping()
                ->castStateUsing(fn (mixed $state): ?string => trim((string) $state) === '' ? null : trim((string) $state))
                ->rules(['required', 'max:255'])
                ->example('Bandung'),
            ImportColumn::make('tanggal_lahir')
                ->label('Tanggal Lahir')
                ->requiredMapping()
                ->castStateUsing(fn (mixed $state): ?string => trim((string) $state) === '' ? null : trim((string) $state))
                ->rules(['required', 'date'])
                ->example('2014-06-15'),
            ImportColumn::make('jenis_kelamin')
                ->label('Jenis Kelamin')
                ->requiredMapping()
                ->castStateUsing(fn (mixed $state): ?string => match (strtolower(trim((string) $state))) {
                    'l', 'laki-laki', 'laki laki' => JenisKelamin::LakiLaki->value,
                    'p', 'perempuan' => JenisKelamin::Perempuan->value,
                    default => null,
                })
                ->rules(['required', Rule::enum(JenisKelamin::class)])
                ->example('Laki-laki'),
            ImportColumn::make('agama')
                ->castStateUsing(fn (mixed $state): ?string => Agama::tryFrom(strtolower(trim((string) $state)))?->value)
                ->rules(['nullable', Rule::enum(Agama::class)])
                ->example('Islam'),
            ImportColumn::make('asal_sekolah')
                ->label('Asal Sekolah')
                ->castStateUsing(fn (mixed $state): ?string => trim((string) $state) === '' ? null : trim((string) $state))
                ->rules(['nullable', 'max:255'])
                ->example('SD Negeri 1 Cisurupan'),
            ImportColumn::make('alamat')
                ->label('Alamat')
                ->requiredMapping()
                ->castStateUsing(fn (mixed $state): ?string => trim((string) $state) === '' ? null : trim((string) $state))
                ->rules(['required', 'max:1000'])
                ->example('Jl. Raya No. 10, Bandung'),
            ImportColumn::make('nomor_telepon')
                ->label('No Telepon')
                ->requiredMapping()
                ->castStateUsing(fn (mixed $state): ?string => trim((string) $state) === '' ? null : trim((string) $state))
                ->rules(['required', 'max:30'])
                ->example('081234567890'),
            ImportColumn::make('nomor_kip_pip')
                ->label('Nomor KIP/PIP')
                ->castStateUsing(fn (mixed $state): ?string => trim((string) $state) === '' ? null : trim((string) $state))
                ->rules(['nullable', 'max:100'])
                ->example('KIP123456'),
            ImportColumn::make('nama_ayah_kandung')
                ->label('Nama Ayah Kandung')
                ->requiredMapping()
                ->castStateUsing(fn (mixed $state): ?string => trim((string) $state) === '' ? null : trim((string) $state))
                ->rules(['required', 'max:255'])
                ->example('Budi Santoso'),
            ImportColumn::make('nama_ibu_kandung')
                ->label('Nama Ibu Kandung')
                ->requiredMapping()
                ->castStateUsing(fn (mixed $state): ?string => trim((string) $state) === '' ? null : trim((string) $state))
                ->rules(['required', 'max:255'])
                ->example('Siti Aminah'),
            ImportColumn::make('nama_wali')
                ->label('Nama Wali')
                ->castStateUsing(fn (mixed $state): ?string => trim((string) $state) === '' ? null : trim((string) $state))
                ->rules(['nullable', 'max:255'])
                ->example(''),
        ];
    }

    public static function getNamaTemplate(): string
    {
        return 'template-ppdb';
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return 'Import PPDB selesai: '.Number::format($import->successful_rows).' baris berhasil.';
    }

    /**
     * Importing the same applicant again for the same academic year updates their registration.
     */
    public function resolveRecord(): Ppdb
    {
        $tahunAjaranId = TahunAjaran::untukPpdb()?->id;

        return Ppdb::query()->firstOrNew(['tahun_ajaran_id' => $tahunAjaranId, 'nisn' => $this->data['nisn'] ?? null]);
    }

    public function fillRecord(): void
    {
        $this->record->fill([
            'nama_lengkap' => $this->data['nama_lengkap'] ?? null,
            'nisn' => $this->data['nisn'] ?? null,
            'nik' => $this->data['nik'] ?? null,
            'tempat_lahir' => $this->data['tempat_lahir'] ?? null,
            'tanggal_lahir' => $this->data['tanggal_lahir'] ?? null,
            'jenis_kelamin' => $this->data['jenis_kelamin'] ?? null,
            'agama' => $this->data['agama'] ?? null,
            'asal_sekolah' => $this->data['asal_sekolah'] ?? null,
            'alamat' => $this->data['alamat'] ?? null,
            'nomor_telepon' => $this->data['nomor_telepon'] ?? null,
            'nomor_kip_pip' => $this->data['nomor_kip_pip'] ?? null,
            'nama_ayah_kandung' => $this->data['nama_ayah_kandung'] ?? null,
            'nama_ibu_kandung' => $this->data['nama_ibu_kandung'] ?? null,
            'nama_wali' => $this->data['nama_wali'] ?? null,
        ]);
    }

    public function saveRecord(): void
    {
        $this->record->save();
    }
}
