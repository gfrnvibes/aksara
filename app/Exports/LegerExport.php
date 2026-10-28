<?php

namespace App\Exports;

use App\Models\MataPelajaran;
use App\Services\Leger\Leger;
use Barryvdh\DomPDF\Facade\Pdf;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Writes a class grade recap (leger) as an Excel sheet or a landscape PDF.
 */
class LegerExport
{
    /**
     * Columns before the subject columns: No, NIS, Nama.
     */
    private const int KOLOM_AWAL = 3;

    /**
     * Write the leger to an .xlsx file and return its temporary path.
     */
    public static function excel(Leger $leger): string
    {
        $path = tempnam(sys_get_temp_dir(), 'leger-');
        $tebal = (new Style)->setFontBold();
        $options = new Options;
        $jumlahMapel = $leger->mataPelajaran->count();
        $kolomNilai = $leger->kolom();
        $lebarMapel = count($kolomNilai);
        $bertingkat = $lebarMapel > 1;

        $options->mergeCells(0, 1, self::KOLOM_AWAL + ($jumlahMapel * $lebarMapel) + 2, 1);

        if ($bertingkat) {
            foreach ([0, 1, 2] as $kolom) {
                $options->mergeCells($kolom, 3, $kolom, 4);
            }

            foreach ($jumlahMapel === 0 ? [] : range(0, $jumlahMapel - 1) as $urutan) {
                $kolom = self::KOLOM_AWAL + ($urutan * $lebarMapel);
                $options->mergeCells($kolom, 3, $kolom + $lebarMapel - 1, 3);
            }

            foreach ([0, 1, 2] as $tambahan) {
                $kolom = self::KOLOM_AWAL + ($jumlahMapel * $lebarMapel) + $tambahan;
                $options->mergeCells($kolom, 3, $kolom, 4);
            }
        }

        $writer = new Writer($options);
        $writer->openToFile($path);

        try {
            $writer->addRow(Row::fromValues([$leger->judul()], $tebal));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues([
                'No', 'NIS', 'Nama Siswa',
                ...$leger->mataPelajaran->flatMap(fn (MataPelajaran $mapel): array => array_pad([$mapel->nama], $lebarMapel, null))->all(),
                'Jumlah', 'Rata-rata', 'Peringkat',
            ], $tebal));

            if ($bertingkat) {
                $writer->addRow(Row::fromValues([
                    null, null, null,
                    ...$leger->mataPelajaran->flatMap(fn (): array => array_values($kolomNilai))->all(),
                    null, null, null,
                ], $tebal));
            }

            foreach ($leger->baris as $urutan => $baris) {
                $writer->addRow(Row::fromValues([
                    $urutan + 1,
                    $baris['siswa']->nis,
                    $baris['siswa']->nama_lengkap,
                    ...$leger->mataPelajaran->flatMap(fn (MataPelajaran $mapel): array => array_values($baris['nilai'][$mapel->id]['skor']))->all(),
                    $baris['rata_rata'] === null ? null : $baris['jumlah'],
                    $baris['rata_rata'],
                    $baris['peringkat'],
                ]));
            }

            $rataRataMapel = $leger->rataRataMapel();

            $writer->addRow(Row::fromValues([
                null, null, 'Rata-rata kelas',
                ...$leger->mataPelajaran->flatMap(fn (MataPelajaran $mapel): array => array_values($rataRataMapel[$mapel->id]))->all(),
            ], $tebal));
        } finally {
            $writer->close();
        }

        return $path;
    }

    public static function pdf(Leger $leger): string
    {
        return Pdf::loadView('cetak.leger', [
            'leger' => $leger,
            'rataRataMapel' => $leger->rataRataMapel(),
        ])
            ->setPaper('a4', 'landscape')
            ->output();
    }
}
