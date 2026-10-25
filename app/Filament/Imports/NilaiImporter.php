<?php

namespace App\Filament\Imports;

use App\Filament\Imports\Concerns\ImportsDataNilai;
use App\Models\Rapor;
use App\Models\Siswa;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;

/**
 * Imports report-card grades, one row per student per semester, for students that already exist.
 *
 * @property Rapor $record
 */
class NilaiImporter extends BukuIndukImporter
{
    use ImportsDataNilai;

    protected static ?string $model = Rapor::class;

    protected ?Siswa $siswa = null;

    public static function getColumns(): array
    {
        return [
            static::kolomNis(),
            ImportColumn::make('nama_lengkap')
                ->label('Nama Lengkap')
                ->helperText('Hanya sebagai keterangan, tidak mengubah data siswa.')
                ->example('Ahmad Fauzi'),
            ...static::kolomNilai(wajib: true),
        ];
    }

    public static function getNamaTemplate(): string
    {
        return 'template-data-nilai-siswa';
    }

    public function resolveRecord(): Rapor
    {
        $this->siswa = Siswa::query()->where('nis', $this->data['nis'])->first();

        if (! $this->siswa) {
            throw new RowImportFailedException("Siswa dengan NIS {$this->data['nis']} belum terdaftar. Import Data Siswa terlebih dahulu.");
        }

        return $this->cariRapor($this->siswa);
    }

    public function fillRecord(): void
    {
        //
    }

    public function saveRecord(): void
    {
        $this->simpanRapor($this->siswa, $this->record);
    }
}
