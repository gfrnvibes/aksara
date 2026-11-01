<?php

namespace App\Filament\Imports;

use App\Enums\JenisKelamin;
use Carbon\CarbonImmutable;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Throwable;

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

    protected static function tanggal(mixed $state): ?string
    {
        $teks = static::teks($state);

        if ($teks === null) {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y-m-d H:i:s'] as $format) {
            try {
                $tanggal = CarbonImmutable::createFromFormat($format, $teks);

                if ($tanggal !== null && $tanggal->format($format) === $teks) {
                    return $tanggal->toDateString();
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $teks;
    }

    protected static function jenisKelamin(mixed $state): ?string
    {
        $teks = Str::lower((string) static::teks($state));

        return match (true) {
            $teks === '' => null,
            in_array($teks, ['l', 'lk', 'laki-laki', 'laki laki', 'laki'], true) => JenisKelamin::LakiLaki->value,
            in_array($teks, ['p', 'pr', 'perempuan', 'wanita'], true) => JenisKelamin::Perempuan->value,
            default => $teks,
        };
    }
}
