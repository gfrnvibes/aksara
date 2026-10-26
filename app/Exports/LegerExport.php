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

        $options->mergeCells(0, 1, self::KOLOM_AWAL + ($jumlahMapel * 2) + 2, 1);

        foreach ([0, 1, 2] as $kolom) {
            $options->mergeCells($kolom, 3, $kolom, 4);
        }

        foreach ($jumlahMapel === 0 ? [] : range(0, $jumlahMapel - 1) as $urutan) {
            $kolom = self::KOLOM_AWAL + ($urutan * 2);
            $options->mergeCells($kolom, 3, $kolom + 1, 3);
        }

        foreach ([0, 1, 2] as $tambahan) {
            $kolom = self::KOLOM_AWAL + ($jumlahMapel * 2) + $tambahan;
            $options->mergeCells($kolom, 3, $kolom, 4);
        }

        $writer = new Writer($options);
        $writer->openToFile($path);

        try {
            $writer->addRow(Row::fromValues([$leger->judul()], $tebal));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues([
                'No', 'NIS', 'Nama Siswa',
                ...$leger->mataPelajaran->flatMap(fn (MataPelajaran $mapel): array => [$mapel->nama, null])->all(),
                'Jumlah', 'Rata-rata', 'Peringkat',
            ], $tebal));
            $writer->addRow(Row::fromValues([
                null, null, null,
                ...$leger->mataPelajaran->flatMap(fn (): array => ['P', 'K'])->all(),
                null, null, null,
            ], $tebal));

            foreach ($leger->baris as $urutan => $baris) {
                $writer->addRow(Row::fromValues([
                    $urutan + 1,
                    $baris['siswa']->nis,
                    $baris['siswa']->nama_lengkap,
                    ...$leger->mataPelajaran->flatMap(fn (MataPelajaran $mapel): array => [
                        $baris['nilai'][$mapel->id]['p'],
                        $baris['nilai'][$mapel->id]['k'],
                    ])->all(),
                    $baris['rata_rata'] === null ? null : $baris['jumlah'],
                    $baris['rata_rata'],
                    $baris['peringkat'],
                ]));
            }

            $rataRataMapel = $leger->rataRataMapel();

            $writer->addRow(Row::fromValues([
                null, null, 'Rata-rata kelas',
                ...$leger->mataPelajaran->flatMap(fn (MataPelajaran $mapel): array => [
                    $rataRataMapel[$mapel->id]['p'],
                    $rataRataMapel[$mapel->id]['k'],
                ])->all(),
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
