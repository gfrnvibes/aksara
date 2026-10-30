<?php

namespace App\Services\Arsip;

use App\Models\Ijazah;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

/**
 * Stores a scanned document on the archive disk. Photos and scans are turned upright, scaled down and saved as
 * JPEG, which usually shrinks a phone photo from several megabytes to a few hundred kilobytes; PDFs are kept as is.
 */
final class SimpanBerkasArsip
{
    /**
     * Longest side of a stored image, in pixels: still sharp enough to read a diploma when zoomed in.
     */
    public const int SISI_TERPANJANG = 2000;

    public const int KUALITAS_JPEG = 80;

    /**
     * @param  int  $sisiTerpanjang  Longest side of a stored image, in pixels.
     * @return string The path on the archive disk.
     */
    public function simpan(UploadedFile $file, string $direktori, int $sisiTerpanjang = self::SISI_TERPANJANG): string
    {
        $disk = Storage::disk(Ijazah::disk());

        if (! str_starts_with((string) $file->getMimeType(), 'image/')) {
            return (string) $disk->putFileAs($direktori, $file, Str::uuid().'.pdf');
        }

        $gambar = ImageManager::gd()
            ->read((string) $file->get())
            ->scaleDown($sisiTerpanjang, $sisiTerpanjang);

        $path = "{$direktori}/".Str::uuid().'.jpg';

        $disk->put($path, (string) $gambar->toJpeg(self::KUALITAS_JPEG));

        return $path;
    }
}
