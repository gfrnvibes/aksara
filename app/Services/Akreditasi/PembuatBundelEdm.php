<?php

namespace App\Services\Akreditasi;

use App\Models\EdmIndikator;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class PembuatBundelEdm
{
    /**
     * Buat arsip ZIP berisi seluruh gambar & dokumen bukti untuk 1 butir EDM (termasuk dari GaleriKegiatan).
     *
     * @return string Path file ZIP sementara yang siap diunduh (deleteFileAfterSend)
     */
    public function buatZipButir(EdmIndikator $indikator): string
    {
        $indikator->loadMissing(['buktis', 'evaluasi.tahunAjaran']);
        $daftarBukti = $indikator->buktiGabungan();

        $tempDir = storage_path('app/temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipPath = $tempDir.'/edm-'.$indikator->kode.'-'.uniqid().'.zip';
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Gagal membuat arsip ZIP bukti EDM.');
        }

        $no = 1;
        foreach ($daftarBukti as $item) {
            $disk = Storage::disk($item['disk']);
            if (! $disk->exists($item['path'])) {
                continue;
            }

            $isi = $disk->get($item['path']);
            if (! is_string($isi)) {
                continue;
            }

            $ext = pathinfo($item['path'], PATHINFO_EXTENSION) ?: 'jpg';
            $slugJudul = str($item['judul'])->slug()->limit(50, '');
            $prefix = str_pad((string) $no, 2, '0', STR_PAD_LEFT);
            $zip->addFromString("{$indikator->kode}/{$prefix}-{$item['sumber']}-{$slugJudul}.{$ext}", $isi);
            $no++;
        }

        $ringkasan = "EVALUASI DIRI MADRASAH (EDM 2026)\n"
            ."Butir: {$indikator->kode}\n"
            ."Pertanyaan: {$indikator->pertanyaan}\n"
            .'Skor: '.($indikator->skor_final ?? $indikator->skor_saran ?? '-')."\n"
            .'Kondisi Nyata: '.($indikator->kondisi_nyata ?? '-')."\n"
            .'Tindak Lanjut: '.($indikator->tindak_lanjut ?? '-')."\n"
            .'Jumlah File Bukti: '.($no - 1)."\n";

        $zip->addFromString("{$indikator->kode}/00-ringkasan-{$indikator->kode}.txt", $ringkasan);
        $zip->close();

        return $zipPath;
    }
}
