<?php

namespace App\Services\Harian;

use Illuminate\Support\Facades\Storage;

class WatermarkFotoPresensi
{
    /**
     * Stamp attendance metadata (teacher name, timestamp, location/distance, coordinates) onto the stored photo.
     *
     * @param  array{nama: string, waktu: string, lokasi?: string|null, jarak?: int|null, latitude?: float|null, longitude?: float|null}  $metadata
     */
    public function terapkan(string $diskPath, array $metadata): void
    {
        $fullPath = Storage::disk('local')->path($diskPath);
        if (! is_file($fullPath)) {
            return;
        }

        $raw = @file_get_contents($fullPath);
        if ($raw === false) {
            return;
        }

        $image = @imagecreatefromstring($raw);
        if ($image === false) {
            return;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        if ($width < 120 || $height < 80) {
            return;
        }

        $lines = $this->susunBaris($metadata);
        $fontPath = $this->cariFont();

        imagealphablending($image, true);
        $bgColor = imagecolorallocatealpha($image, 6, 40, 30, 35);
        $textColor = imagecolorallocate($image, 255, 255, 255);
        $accentColor = imagecolorallocate($image, 253, 230, 138);

        if ($bgColor === false || $textColor === false || $accentColor === false) {
            return;
        }

        if ($fontPath !== null) {
            $fontSize = max(11, (int) round($width / 46));
            $lineHeight = (int) round($fontSize * 1.6);
            $padding = (int) round($fontSize * 1.1);
            $barHeight = ($lineHeight * count($lines)) + ($padding * 2);
            $barTop = max(0, $height - $barHeight);

            imagefilledrectangle($image, 0, $barTop, $width, $height, $bgColor);

            foreach ($lines as $index => $line) {
                $y = $barTop + $padding + $fontSize + ($index * $lineHeight);
                $color = $index === 0 ? $accentColor : $textColor;
                imagettftext($image, $fontSize, 0, $padding, $y, $color, $fontPath, $line);
            }
        } else {
            $font = $width >= 600 ? 5 : 3;
            $lineHeight = imagefontheight($font) + 6;
            $padding = 10;
            $barHeight = ($lineHeight * count($lines)) + ($padding * 2);
            $barTop = max(0, $height - $barHeight);

            imagefilledrectangle($image, 0, $barTop, $width, $height, $bgColor);

            foreach ($lines as $index => $line) {
                $y = $barTop + $padding + ($index * $lineHeight);
                $color = $index === 0 ? $accentColor : $textColor;
                imagestring($image, $font, $padding, $y, $line, $color);
            }
        }

        imagejpeg($image, $fullPath, 85);
    }

    /**
     * @param  array{nama: string, waktu: string, lokasi?: string|null, jarak?: int|null, latitude?: float|null, longitude?: float|null}  $metadata
     * @return list<string>
     */
    protected function susunBaris(array $metadata): array
    {
        $lines = [
            $metadata['nama'],
            'Waktu: '.$metadata['waktu'],
        ];

        $lokasiParts = [];
        if (filled($metadata['lokasi'] ?? null)) {
            $lokasiText = (string) $metadata['lokasi'];
            if (isset($metadata['jarak'])) {
                $lokasiText .= " ({$metadata['jarak']} m)";
            }
            $lokasiParts[] = $lokasiText;
        } elseif (isset($metadata['jarak'])) {
            $lokasiParts[] = "Jarak: {$metadata['jarak']} m";
        }

        if (isset($metadata['latitude'], $metadata['longitude'])) {
            $lokasiParts[] = number_format($metadata['latitude'], 6).', '.number_format($metadata['longitude'], 6);
        }

        if ($lokasiParts !== []) {
            $lines[] = implode(' · ', $lokasiParts);
        }

        return $lines;
    }

    protected function cariFont(): ?string
    {
        $candidates = [
            'C:/Windows/Fonts/arial.ttf',
            'C:/Windows/Fonts/segoeui.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
