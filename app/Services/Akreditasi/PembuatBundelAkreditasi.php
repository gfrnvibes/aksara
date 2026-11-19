<?php

namespace App\Services\Akreditasi;

use App\Models\AkreditasiBukti;
use App\Models\AkreditasiDokumen;
use App\Models\TahunAjaran;
use App\Services\Surat\PengaturanKop;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;
use RuntimeException;
use ZipArchive;

class PembuatBundelAkreditasi
{
    public function __construct(protected PetaBanPdm $peta) {}

    /**
     * Build the complete accreditation ZIP archive for the given academic year and return its temporary path.
     */
    public function buatZip(TahunAjaran $tahun): string
    {
        $this->peta->inisialisasiDokumen($tahun);

        $dokumens = AkreditasiDokumen::query()
            ->where('tahun_ajaran_id', $tahun->id)
            ->with(['tahunAjaran', 'kepalaMadrasah', 'ketuaTim'])
            ->get()
            ->keyBy('kode');

        $buktis = AkreditasiBukti::query()
            ->where('tahun_ajaran_id', $tahun->id)
            ->orderBy('kode_butir')
            ->orderBy('id')
            ->get()
            ->groupBy('kode_butir');

        $statistik = $this->peta->ringkasanStatistik($tahun);
        $kesiapan = $this->peta->analisisKesiapan($tahun);
        $kop = PengaturanKop::untuk('surat_keterangan');

        $namaMadrasah = Str::slug((string) config('sekolah.nama_singkat', 'Madrasah'), '_');
        $tahunSlug = Str::slug($tahun->nama, '_');
        $root = "Bundel_Akreditasi_BANPDM_{$namaMadrasah}_{$tahunSlug}";

        $zipPath = tempnam(sys_get_temp_dir(), 'akreditasi-zip-');
        if ($zipPath === false) {
            throw new RuntimeException('Gagal membuat file sementara untuk bundel akreditasi.');
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Gagal membuka arsip ZIP akreditasi.');
        }

        $tempFiles = [];

        try {
            // 1. 01_Dokumen_Wajib_Sispena/ (6 PDF)
            $namaFileDw = [
                'dw_1' => '01_KSP_MTs.pdf',
                'dw_2' => '02_RKT_Madrasah.pdf',
                'dw_3' => '03_RKAS_Madrasah.pdf',
                'dw_4' => '04_Kalender_Pendidikan.pdf',
                'dw_5' => '05_Sampel_Modul_Ajar_dan_P5PPRA.pdf',
                'dw_6' => '06_Dokumentasi_Lingkungan_Belajar.pdf',
            ];

            foreach ($namaFileDw as $kode => $namaFile) {
                $dok = $dokumens->get($kode);
                if ($dok instanceof AkreditasiDokumen) {
                    $pdfBytes = Pdf::loadView('cetak.akreditasi-dokumen', [
                        'dokumen' => $dok,
                        'kop' => $kop,
                    ])->setPaper('a4', 'portrait')->output();

                    $zip->addFromString("{$root}/01_Dokumen_Wajib_Sispena/{$namaFile}", $pdfBytes);
                }
            }

            // 2. 02_Draft_Isian_DKA_14_Butir.docx
            $docxPath = $this->buatDocxDka($tahun, $dokumens);
            $tempFiles[] = $docxPath;
            $zip->addFile($docxPath, "{$root}/02_Draft_Isian_DKA_14_Butir.docx");

            // 3. 02_Mapping_Bukti_14_Butir.xlsx
            $xlsxPath = $this->buatXlsxMapping($dokumens, $buktis);
            $tempFiles[] = $xlsxPath;
            $zip->addFile($xlsxPath, "{$root}/02_Mapping_Bukti_14_Butir.xlsx");

            // 4. 03_Folder_Bukti_Per_Butir/Butir_01..14/
            foreach (PetaBanPdm::butir14() as $butir) {
                $folder = sprintf('Butir_%02d', $butir['nomor']);
                $daftarBukti = $buktis->get($butir['kode'], collect());

                $indeks = "BUTIR {$butir['nomor']}: {$butir['judul']}\n"
                    ."Komponen {$butir['komponen']}: {$butir['komponen_nama']}\n"
                    ."Bukti Kunci: {$butir['bukti_kunci']}\n"
                    .str_repeat('-', 50)."\n";

                if ($daftarBukti->isEmpty()) {
                    $indeks .= "Belum ada bukti yang ditandai untuk butir ini.\n";
                } else {
                    foreach ($daftarBukti as $i => $item) {
                        $indeks .= ($i + 1).". [{$item->sumber_tipe}] {$item->judul}";
                        if (filled($item->keterangan)) {
                            $indeks .= " — {$item->keterangan}";
                        }
                        $indeks .= "\n";

                        if (filled($item->berkas_path) && Storage::disk('local')->exists($item->berkas_path)) {
                            $isiBerkas = Storage::disk('local')->get($item->berkas_path);
                            if ($isiBerkas !== null) {
                                $zip->addFromString("{$root}/03_Folder_Bukti_Per_Butir/{$folder}/".basename($item->berkas_path), $isiBerkas);
                            }
                        }
                    }
                }

                $zip->addFromString("{$root}/03_Folder_Bukti_Per_Butir/{$folder}/00_Daftar_Bukti.txt", $indeks);
            }

            // 5. 04_Deck_Presentasi_Kepala_Madrasah.pdf (16:9 Landscape)
            $deckBytes = Pdf::loadView('cetak.akreditasi-deck', [
                'tahun' => $tahun,
                'statistik' => $statistik,
                'kesiapan' => $kesiapan,
                'kop' => $kop,
            ])->setPaper('a4', 'landscape')->output();
            $zip->addFromString("{$root}/04_Deck_Presentasi_Kepala_Madrasah.pdf", $deckBytes);

            // 6. 05_Lembar_Ceklis_Kesiapan_Lapangan.pdf
            $ceklisBytes = Pdf::loadView('cetak.akreditasi-ceklis', [
                'tahun' => $tahun,
                'dokumens' => $dokumens,
                'buktis' => $buktis,
                'kesiapan' => $kesiapan,
                'kop' => $kop,
            ])->setPaper('a4', 'portrait')->output();
            $zip->addFromString("{$root}/05_Lembar_Ceklis_Kesiapan_Lapangan.pdf", $ceklisBytes);

            $zip->close();
        } finally {
            foreach ($tempFiles as $file) {
                @unlink($file);
            }
        }

        return $zipPath;
    }

    /**
     * @param  \Illuminate\Support\Collection<string, AkreditasiDokumen>  $dokumens
     */
    protected function buatDocxDka(TahunAjaran $tahun, $dokumens): string
    {
        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection();
        $section->addTitle('DRAFT ISIAN DESKRIPSI KINERJA ASESI (DKA) — 14 BUTIR BAN-PDM', 1);
        $section->addText(config('sekolah.nama', 'Madrasah Tsanawiyah')." · Tahun Ajaran {$tahun->nama}");
        $section->addTextBreak(1);

        foreach (PetaBanPdm::butir14() as $butir) {
            $dok = $dokumens->get($butir['kode']);
            $section->addTitle("Butir {$butir['nomor']}: {$butir['judul']}", 2);
            $section->addText("Komponen {$butir['komponen']}: {$butir['komponen_nama']}", ['italic' => true, 'size' => 10]);

            $isiHtml = $dok?->isi_html;
            if (filled($isiHtml)) {
                Html::addHtml($section, $isiHtml, false, false);
            } else {
                $section->addText("Belum disusun. Bukti kunci yang diperlukan: {$butir['bukti_kunci']}");
            }

            $section->addTextBreak(1);
        }

        $path = tempnam(sys_get_temp_dir(), 'akreditasi-dka-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
    }

    /**
     * @param  \Illuminate\Support\Collection<int|string, AkreditasiDokumen>  $dokumens
     * @param  \Illuminate\Support\Collection<int|string, \Illuminate\Database\Eloquent\Collection<int, AkreditasiBukti>>  $buktis
     */
    protected function buatXlsxMapping($dokumens, $buktis): string
    {
        $path = tempnam(sys_get_temp_dir(), 'akreditasi-xlsx-').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);

        $bold = (new Style)->setFontBold();
        $writer->addRow(Row::fromValues(['No', 'Kode Butir', 'Komponen', 'Indikator BAN-PDM', 'Status Narasi DKA', 'Jumlah Bukti Ter-tag', 'Bukti Kunci'], $bold));

        foreach (PetaBanPdm::butir14() as $butir) {
            $dok = $dokumens->get($butir['kode']);
            $jumlahBukti = $buktis->get($butir['kode'], collect())->count();

            $writer->addRow(Row::fromValues([
                $butir['nomor'],
                $butir['kode'],
                $butir['komponen_nama'],
                $butir['judul'],
                strtoupper($dok instanceof AkreditasiDokumen ? $dok->status : 'belum'),
                $jumlahBukti,
                $butir['bukti_kunci'],
            ]));
        }

        $writer->close();

        return $path;
    }
}
