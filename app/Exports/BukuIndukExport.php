<?php

namespace App\Exports;

use App\Enums\IsiDokumen;
use App\Enums\JenisOrangTua;
use App\Filament\Imports\BukuIndukGabunganImporter;
use App\Filament\Imports\NilaiImporter;
use App\Filament\Imports\SiswaImporter;
use App\Models\MataPelajaran;
use App\Models\Rapor;
use App\Models\Siswa;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use DateTimeInterface;
use Filament\Actions\Imports\ImportColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Produces the Excel and PDF exports and the printable Buku Induk documents.
 *
 * Excel headers are the import column labels, so every export can be edited and imported back.
 */
class BukuIndukExport
{
    /**
     * Eager-load everything the exports and documents read from a student.
     *
     * @param  Builder<Siswa>  $query
     * @return Collection<int, Siswa>
     */
    public static function muatSiswa(Builder $query): Collection
    {
        return $query
            ->with([
                'kelas',
                'ayah',
                'ibu',
                'wali',
                'rapor' => fn ($rapor) => $rapor->with(['tahunAjaran', 'kelas', 'nilaiRapor', 'nilaiEkstrakurikuler.ekstrakurikuler']),
            ])
            ->orderBy('nis')
            ->get()
            ->toBase();
    }

    /**
     * Eager-load everything the exports read from a report card.
     *
     * @param  Builder<Rapor>  $query
     * @return Collection<int, Rapor>
     */
    public static function muatRapor(Builder $query): Collection
    {
        return static::urutkanRapor($query->with(['siswa', 'tahunAjaran', 'kelas', 'nilaiRapor', 'nilaiEkstrakurikuler'])->get()->toBase());
    }

    /**
     * Write the selected content to an .xlsx file and return its temporary path.
     *
     * @param  Collection<int, Siswa>  $daftarSiswa
     */
    public static function excelSiswa(Collection $daftarSiswa, IsiDokumen $isi): string
    {
        return match ($isi) {
            IsiDokumen::Siswa => static::tulisExcel(
                SiswaImporter::getColumns(),
                $daftarSiswa->map(fn (Siswa $siswa): array => static::barisSiswa($siswa)),
            ),
            IsiDokumen::Nilai => static::excelRapor(static::raporDari($daftarSiswa)),
            IsiDokumen::Keduanya => static::tulisExcel(
                BukuIndukGabunganImporter::getColumns(),
                $daftarSiswa->flatMap(fn (Siswa $siswa): array => $siswa->rapor->isEmpty()
                    ? [static::barisSiswa($siswa)]
                    : static::urutkanRapor($siswa->rapor->each->setRelation('siswa', $siswa)->toBase())
                        ->map(fn (Rapor $rapor): array => [...static::barisSiswa($siswa), ...static::barisRapor($rapor)])
                        ->all()),
            ),
        };
    }

    /**
     * @param  Collection<int, Rapor>  $daftarRapor
     */
    public static function excelRapor(Collection $daftarRapor): string
    {
        return static::tulisExcel(
            NilaiImporter::getColumns(),
            $daftarRapor->map(fn (Rapor $rapor): array => [
                'nis' => $rapor->siswa->nis,
                'nama_lengkap' => $rapor->siswa->nama_lengkap,
                ...static::barisRapor($rapor),
            ]),
        );
    }

    /**
     * Render the tabular recap (student list and/or grade recap) as a landscape PDF.
     *
     * @param  Collection<int, Siswa>  $daftarSiswa
     * @param  Collection<int, Rapor>|null  $daftarRapor
     */
    public static function pdfRekap(Collection $daftarSiswa, IsiDokumen $isi, ?Collection $daftarRapor = null): string
    {
        $daftarRapor ??= static::raporDari($daftarSiswa);

        return Pdf::loadView('cetak.rekap', [
            'isi' => $isi,
            'daftarSiswa' => $daftarSiswa,
            'daftarRapor' => $daftarRapor,
            'mataPelajaran' => static::mataPelajaranDari($daftarRapor),
        ])
            ->setPaper('a4', 'landscape')
            ->output();
    }

    /**
     * Render the full Buku Induk sheets of every student as one PDF.
     *
     * @param  Collection<int, Siswa>  $daftarSiswa
     */
    public static function pdfBukuInduk(Collection $daftarSiswa, IsiDokumen $isi): string
    {
        return Pdf::loadView('cetak.buku-induk', static::dataBukuInduk($daftarSiswa, $isi, untukPdf: true))
            ->setPaper('a4')
            ->output();
    }

    /**
     * View data for the printable Buku Induk sheets.
     *
     * @param  Collection<int, Siswa>  $daftarSiswa
     * @return array<string, mixed>
     */
    public static function dataBukuInduk(Collection $daftarSiswa, IsiDokumen $isi, bool $untukPdf = false): array
    {
        return [
            'daftarSiswa' => $daftarSiswa,
            'isi' => $isi,
            'untukPdf' => $untukPdf,
            'mataPelajaran' => static::mataPelajaranDari(static::raporDari($daftarSiswa))
                ->groupBy(fn (MataPelajaran $mataPelajaran): string => $mataPelajaran->kelompok->value),
        ];
    }

    public static function namaFile(string $awalan, IsiDokumen $isi, string $ekstensi): string
    {
        return Str::slug("{$awalan} {$isi->value}").'-'.now()->format('Ymd-His').".{$ekstensi}";
    }

    /**
     * @return array<string, mixed>
     */
    protected static function barisSiswa(Siswa $siswa): array
    {
        $baris = [
            'nis' => $siswa->nis,
            'kelas' => $siswa->kelas?->nama,
        ];

        foreach (SiswaImporter::getColumns() as $kolom) {
            $nama = $kolom->getName();

            if (array_key_exists($nama, $baris)) {
                continue;
            }

            [$jenis, $atribut] = array_pad(explode('_', $nama, 2), 2, null);

            $baris[$nama] = JenisOrangTua::tryFrom($jenis) !== null
                ? $siswa->{$jenis}?->{$atribut}
                : $siswa->getAttribute($nama);
        }

        return $baris;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function barisRapor(Rapor $rapor): array
    {
        $baris = [
            'tahun_ajaran' => $rapor->tahunAjaran->nama,
            'semester' => $rapor->semester,
            'kelas_rapor' => $rapor->kelas->nama,
            'sakit' => $rapor->sakit,
            'izin' => $rapor->izin,
            'tanpa_keterangan' => $rapor->tanpa_keterangan,
            'nilai_akhlak' => $rapor->nilai_akhlak,
            'nilai_kepribadian' => $rapor->nilai_kepribadian,
            'keputusan' => $rapor->keputusan,
            'catatan' => $rapor->catatan,
        ];

        foreach ($rapor->nilaiRapor as $nilai) {
            $baris["mapel_{$nilai->mata_pelajaran_id}_pengetahuan"] = $nilai->nilai_pengetahuan;
            $baris["mapel_{$nilai->mata_pelajaran_id}_keterampilan"] = $nilai->nilai_keterampilan;
        }

        foreach ($rapor->nilaiEkstrakurikuler as $nilai) {
            $baris["ekskul_{$nilai->ekstrakurikuler_id}"] = $nilai->nilai;
        }

        return $baris;
    }

    /**
     * @param  array<int, ImportColumn>  $kolom
     * @param  iterable<int, array<string, mixed>>  $baris
     */
    protected static function tulisExcel(array $kolom, iterable $baris): string
    {
        $path = tempnam(sys_get_temp_dir(), 'buku-induk-export-');

        $writer = new Writer;
        $writer->openToFile($path);

        try {
            $writer->addRow(Row::fromValues(
                array_values(array_map(fn (ImportColumn $kolom): string => (string) $kolom->getLabel(), $kolom)),
                (new Style)->setFontBold(),
            ));

            foreach ($baris as $nilai) {
                $writer->addRow(new Row(array_map(
                    fn (ImportColumn $kolom): Cell => Cell::fromValue(static::nilaiSel($nilai[$kolom->getName()] ?? null)),
                    $kolom,
                )));
            }
        } finally {
            $writer->close();
        }

        return $path;
    }

    protected static function nilaiSel(mixed $nilai): string|int|float|null
    {
        return match (true) {
            $nilai instanceof DateTimeInterface => $nilai->format('Y-m-d'),
            $nilai instanceof BackedEnum => $nilai->value,
            is_bool($nilai) => (int) $nilai,
            default => $nilai,
        };
    }

    /**
     * @param  Collection<int, Siswa>  $daftarSiswa
     * @return Collection<int, Rapor>
     */
    protected static function raporDari(Collection $daftarSiswa): Collection
    {
        return static::urutkanRapor($daftarSiswa->flatMap(fn (Siswa $siswa) => $siswa->rapor->each->setRelation('siswa', $siswa)));
    }

    /**
     * @param  Collection<int, Rapor>  $daftarRapor
     * @return Collection<int, Rapor>
     */
    protected static function urutkanRapor(Collection $daftarRapor): Collection
    {
        return $daftarRapor
            ->sortBy(fn (Rapor $rapor): string => $rapor->siswa->nis.'|'.$rapor->tahunAjaran->nama.'|'.$rapor->semester->value)
            ->values();
    }

    /**
     * Subjects that appear in the given report cards, in report-card order.
     *
     * @param  Collection<int, Rapor>  $daftarRapor
     * @return Collection<int, MataPelajaran>
     */
    protected static function mataPelajaranDari(Collection $daftarRapor): Collection
    {
        $ids = $daftarRapor->flatMap->nilaiRapor->pluck('mata_pelajaran_id')->unique();

        return MataPelajaran::query()->whereIn('id', $ids)->orderBy('kelompok')->orderBy('urutan')->get()->toBase();
    }
}
