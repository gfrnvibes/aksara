<?php

namespace App\Filament\Imports;

use App\Filament\Imports\Concerns\ImportsDataSiswa;
use App\Models\Siswa;

/**
 * Imports student biodata and parents, one row per student, matched by NIS.
 *
 * @property Siswa $record
 */
class SiswaImporter extends BukuIndukImporter
{
    use ImportsDataSiswa;

    protected static ?string $model = Siswa::class;

    public static function getColumns(): array
    {
        return static::kolomSiswa();
    }

    public static function getNamaTemplate(): string
    {
        return 'template-data-siswa';
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
    }
}
