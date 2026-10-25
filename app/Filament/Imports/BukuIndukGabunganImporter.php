<?php

namespace App\Filament\Imports;

use App\Filament\Imports\Concerns\ImportsDataNilai;
use App\Filament\Imports\Concerns\ImportsDataSiswa;
use App\Models\Siswa;

/**
 * Imports biodata and grades from one sheet: one row per student per semester.
 *
 * The biodata is repeated on every semester row; rows without Tahun Ajaran and Semester only update the biodata.
 *
 * @property Siswa $record
 */
class BukuIndukGabunganImporter extends BukuIndukImporter
{
    use ImportsDataNilai;
    use ImportsDataSiswa;

    protected static ?string $model = Siswa::class;

    public static function getColumns(): array
    {
        return [
            ...static::kolomSiswa(),
            ...static::kolomNilai(wajib: false),
        ];
    }

    public static function getNamaTemplate(): string
    {
        return 'template-data-siswa-dan-nilai';
    }

    public function resolveRecord(): Siswa
    {
        return $this->resolveSiswa();
    }

    public function fillRecord(): void
    {
        $this->isiSiswa($this->record);
    }

    public function saveRecord(): void
    {
        $this->record->save();

        $this->simpanOrangTua($this->record);

        if ($this->adaDataRapor()) {
            $this->simpanRapor($this->record, $this->cariRapor($this->record));
        }
    }
}
