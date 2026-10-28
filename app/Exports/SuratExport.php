<?php

namespace App\Exports;

use App\Enums\Semester;
use App\Filament\Resources\Kelas\KelasResource;
use App\Models\MutasiSiswa;
use App\Models\NilaiRapor;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds the letters the madrasah issues from Buku Induk data: proof of enrolment, transfer letter and transcript.
 *
 * @phpstan-type Isian array{nomor?: string|null, keperluan?: string|null, tanggal?: string|null}
 * @phpstan-type Surat array{view: view-string, data: array<string, mixed>, namaFile: string}
 */
class SuratExport
{
    /**
     * @param  Isian  $isian
     * @return Surat
     */
    public static function keteranganAktif(Siswa $siswa, array $isian): array
    {
        $siswa->loadMissing(['kelas', 'ayah', 'ibu', 'wali']);
        $tanggal = static::tanggal($isian);

        return [
            'view' => 'cetak.surat-keterangan-aktif',
            'data' => [
                ...static::dataUmum('Surat Keterangan Aktif', $isian, $tanggal, TahunAjaran::query()->where('is_aktif', true)->first()),
                'siswa' => $siswa,
                'semester' => Semester::berdasarkanTanggal($tanggal),
                'keperluan' => $isian['keperluan'] ?? null,
            ],
            'namaFile' => static::namaFile('surat keterangan aktif', $siswa),
        ];
    }

    /**
     * @param  Isian  $isian
     * @return Surat
     */
    public static function pindah(MutasiSiswa $mutasi, array $isian): array
    {
        $mutasi->loadMissing(['siswa.kelas', 'siswa.ayah', 'siswa.ibu', 'siswa.wali', 'kelas', 'tahunAjaran']);

        return [
            'view' => 'cetak.surat-pindah',
            'data' => [
                ...static::dataUmum(
                    'Surat Keterangan Pindah Sekolah',
                    [...$isian, 'nomor' => $isian['nomor'] ?? $mutasi->nomor_surat],
                    static::tanggal($isian, $mutasi->tanggal),
                    $mutasi->tahunAjaran ?? TahunAjaran::query()->where('is_aktif', true)->first(),
                ),
                'mutasi' => $mutasi,
            ],
            'namaFile' => static::namaFile('surat pindah', $mutasi->siswa),
        ];
    }

    /**
     * Every semester's score per subject: the final score for Kurikulum Merdeka, the mean of knowledge and skill
     * for K13, in the order the semesters were taken.
     *
     * @param  Isian  $isian
     * @return Surat
     */
    public static function transkrip(Siswa $siswa, array $isian): array
    {
        $daftarRapor = $siswa->rapor()
            ->with(['tahunAjaran', 'kelas', 'nilaiRapor.mataPelajaran'])
            ->get()
            ->sortBy(fn (Rapor $rapor): array => [$rapor->tahunAjaran->nama, $rapor->semester->value])
            ->values();

        $nilaiPerRapor = $daftarRapor->map(fn (Rapor $rapor): Collection => $rapor->nilaiRapor
            ->mapWithKeys(fn (NilaiRapor $nilai): array => [$nilai->mata_pelajaran_id => static::nilaiSemester($rapor, $nilai)]));

        $skemaTerakhir = $daftarRapor->last()?->skema();

        $mapelPerKelompok = $daftarRapor
            ->flatMap(fn (Rapor $rapor) => $rapor->nilaiRapor->pluck('mataPelajaran'))
            ->unique('id')
            ->sortBy(fn ($mapel): array => [$mapel->kelompok->value, $mapel->urutan])
            ->groupBy(fn ($mapel): string => $skemaTerakhir?->labelKelompok($mapel->kelompok) ?? $mapel->kelompok->getLabel())
            ->map(fn (Collection $daftarMapel): array => $daftarMapel
                ->map(function ($mapel) use ($nilaiPerRapor): array {
                    $nilai = $nilaiPerRapor->map(fn (Collection $nilaiRapor): ?int => $nilaiRapor->get($mapel->id))->all();
                    $terisi = array_filter($nilai, is_int(...));

                    return ['nama' => $mapel->nama, 'nilai' => $nilai, 'rata' => $terisi === [] ? null : array_sum($terisi) / count($terisi)];
                })
                ->values()
                ->all())
            ->all();

        $rataMapel = array_filter(array_column(array_merge(...array_values($mapelPerKelompok) ?: [[]]), 'rata'), is_float(...));

        return [
            'view' => 'cetak.transkrip',
            'data' => [
                ...static::dataUmum('Transkrip Nilai', $isian, static::tanggal($isian), $daftarRapor->last()->tahunAjaran ?? TahunAjaran::query()->where('is_aktif', true)->first()),
                'siswa' => $siswa,
                'kolomSemester' => $daftarRapor->map(fn (Rapor $rapor): array => [
                    'label' => (KelasResource::getTingkatOptions()[$rapor->kelas->tingkat] ?? $rapor->kelas->tingkat).' / '.$rapor->semester->value,
                    'tahun' => $rapor->tahunAjaran->nama,
                ])->all(),
                'mapelPerKelompok' => $mapelPerKelompok,
                'rataRataKeseluruhan' => $rataMapel === [] ? null : array_sum($rataMapel) / count($rataMapel),
            ],
            'namaFile' => static::namaFile('transkrip nilai', $siswa),
        ];
    }

    /**
     * @param  Surat  $surat
     */
    public static function pdf(array $surat): string
    {
        return Pdf::loadView($surat['view'], [...$surat['data'], 'untukPdf' => true])->setPaper('a4')->output();
    }

    protected static function nilaiSemester(Rapor $rapor, NilaiRapor $nilai): ?int
    {
        $skor = $rapor->skema()->nilaiUntukRataRata($nilai);

        return $skor === [] ? null : (int) round(array_sum($skor) / count($skor));
    }

    /**
     * @param  Isian  $isian
     * @return array<string, mixed>
     */
    protected static function dataUmum(string $judul, array $isian, CarbonInterface $tanggal, ?TahunAjaran $tahunAjaran): array
    {
        return [
            'judul' => $judul,
            'nomorSurat' => $isian['nomor'] ?? null,
            'tanggalSurat' => $tanggal,
            'tahunAjaran' => $tahunAjaran,
        ];
    }

    /**
     * @param  Isian  $isian
     */
    protected static function tanggal(array $isian, ?CarbonInterface $bawaan = null): CarbonInterface
    {
        return filled($isian['tanggal'] ?? null) ? Carbon::parse($isian['tanggal']) : ($bawaan ?? now());
    }

    protected static function namaFile(string $jenis, Siswa $siswa): string
    {
        return Str::slug("{$jenis} {$siswa->nis} {$siswa->nama_lengkap}").'.pdf';
    }
}
