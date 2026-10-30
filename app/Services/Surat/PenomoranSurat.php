<?php

namespace App\Services\Surat;

use App\Enums\JenisSuratKeluar;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use Carbon\CarbonInterface;

/**
 * Numbers outgoing letters and the incoming-letter agenda. Both sequences restart every year; the year's unique
 * index keeps two letters saved at the same moment from sharing a number.
 */
final class PenomoranSurat
{
    private const array BULAN_ROMAWI = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    public function beriNomorSuratKeluar(SuratKeluar $surat): void
    {
        $surat->tahun = $surat->tanggal->year;
        $surat->nomor_urut = $this->berikutnya(SuratKeluar::class, 'nomor_urut', $surat->tahun);
        $surat->nomor = $this->formatNomor($surat->jenis, $surat->tanggal, $surat->nomor_urut);
    }

    public function beriNomorAgenda(SuratMasuk $surat): void
    {
        $surat->tahun = $surat->tanggal_diterima->year;
        $surat->nomor_agenda = $this->berikutnya(SuratMasuk::class, 'nomor_agenda', $surat->tahun);
    }

    /**
     * The number the next outgoing letter of that date would get, for the preview before it is saved.
     */
    public function pratinjauNomor(JenisSuratKeluar $jenis, CarbonInterface $tanggal): string
    {
        return $this->formatNomor($jenis, $tanggal, $this->berikutnya(SuratKeluar::class, 'nomor_urut', $tanggal->year));
    }

    public function formatNomor(JenisSuratKeluar $jenis, CarbonInterface $tanggal, int $urut): string
    {
        return strtr((string) config('sekolah.format_nomor_surat'), [
            '{nomor}' => str_pad((string) $urut, 3, '0', STR_PAD_LEFT),
            '{kode}' => $jenis->kode(),
            '{instansi}' => (string) config('sekolah.kode_instansi_surat'),
            '{bulan}' => self::BULAN_ROMAWI[$tanggal->month],
            '{tahun}' => (string) $tanggal->year,
        ]);
    }

    /**
     * @param  class-string<SuratKeluar|SuratMasuk>  $model
     */
    private function berikutnya(string $model, string $kolom, int $tahun): int
    {
        return (int) $model::query()->where('tahun', $tahun)->max($kolom) + 1;
    }
}
