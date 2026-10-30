<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;

/**
 * A record holding scanned files in its `berkas` column (a list of paths on the archive disk). Files taken off the
 * record, or left by a deleted record, are removed from storage; files are opened through short-lived links.
 */
trait MemilikiBerkasArsip
{
    /**
     * Minutes a preview or download link stays valid.
     */
    public const int MASA_BERLAKU_TAUTAN = 10;

    protected static function bootMemilikiBerkasArsip(): void
    {
        static::updated(function (self $record): void {
            $lama = (array) ($record->getOriginal('berkas') ?? []);

            static::hapusBerkas(array_values(array_diff($lama, $record->berkas ?? [])));
        });

        static::deleted(fn (self $record) => static::hapusBerkas($record->berkas ?? []));
    }

    /**
     * The disk scanned documents are kept on (Cloudflare R2 in production).
     */
    public static function disk(): string
    {
        return (string) config('filesystems.arsip', 'local');
    }

    /**
     * A short-lived link to one of the record's files, so the file goes straight from storage to the browser.
     */
    public function tautanSementara(string $path): string
    {
        return Storage::disk(static::disk())->temporaryUrl($path, now()->addMinutes(self::MASA_BERLAKU_TAUTAN));
    }

    public static function adalahPdf(string $path): bool
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf';
    }

    /**
     * The files of the record, each with a short-lived link, for the preview modal.
     *
     * @return list<array{url: string, pdf: bool, nama: string}>
     */
    public function berkasUntukPratinjau(): array
    {
        return array_map(fn (string $path): array => [
            'url' => $this->tautanSementara($path),
            'pdf' => static::adalahPdf($path),
            'nama' => basename($path),
        ], $this->berkas ?? []);
    }

    /**
     * @param  list<string>  $daftarBerkas
     */
    protected static function hapusBerkas(array $daftarBerkas): void
    {
        if ($daftarBerkas !== []) {
            Storage::disk(static::disk())->delete($daftarBerkas);
        }
    }
}
