<?php

namespace App\Filament\Imports;

use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

/**
 * Shared helpers for the Buku Induk importers, which run synchronously through romansulzhyk/filament-import.
 */
abstract class BukuIndukImporter extends Importer
{
    /**
     * The file name, without extension, offered when the template is downloaded.
     */
    abstract public static function getNamaTemplate(): string;

    public static function kolomNis(): ImportColumn
    {
        return ImportColumn::make('nis')
            ->label('NIS')
            ->requiredMapping()
            ->castStateUsing(fn (mixed $state): ?string => static::teks($state))
            ->rules(['required', 'max:30'])
            ->example('250001');
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Import selesai: '.Number::format($import->successful_rows).' baris berhasil.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' baris gagal.';
        }

        return $body;
    }

    /**
     * Whether the column is mapped to a file header and present in the current row.
     */
    protected function kolomTerpetakan(string $kolom): bool
    {
        return filled($this->columnMap[$kolom] ?? null) && array_key_exists($kolom, $this->data);
    }

    protected static function teks(mixed $state): ?string
    {
        $teks = trim((string) $state);

        return $teks === '' ? null : $teks;
    }
}
