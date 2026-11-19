<?php

namespace App\Services\Akreditasi;

use App\Models\AkreditasiDokumen;
use App\Services\Gemini\KlienGemini;
use Throwable;

class PenyusunDokumenAkreditasiAi
{
    public function __construct(
        protected PetaBanPdm $peta,
        protected ?KlienGemini $klien = null,
    ) {
        $this->klien ??= app(KlienGemini::class);
    }

    /**
     * Generate data-grounded HTML content for an accreditation document (DW or DKA Butir) using Gemini AI,
     * falling back to a structured template if Gemini is unavailable.
     */
    public function susun(AkreditasiDokumen $dokumen): string
    {
        $statistik = $this->peta->ringkasanStatistik($dokumen->tahunAjaran);
        $konteksJson = json_encode($statistik, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';
        $pakaiAi = false;
        $html = '';

        if (KlienGemini::aktif()) {
            try {
                $prompt = $this->bangunPrompt($dokumen, $konteksJson);
                $respons = $this->klien->hasilkan(
                    'Anda adalah konsultan akreditasi BAN-PDM dan pengembang Kurikulum Merdeka Madrasah Tsanawiyah (KMA 450/2024 & IASP 2024). '
                    .'Tugas Anda menyusun draf dokumen resmi madrasah dalam HTML bersih (gunakan tag <h2>, <h3>, <p>, <ul>, <li>, <table>, <tr>, <th>, <td>) '
                    .'yang HANYA berlandaskan angka & fakta JSON yang diberikan tanpa mengarang angka palsu.',
                    $prompt,
                );

                if (filled($respons)) {
                    $html = $this->bersihkanHtml($respons);
                    $pakaiAi = true;
                }
            } catch (Throwable) {
                $pakaiAi = false;
            }
        }

        if ($html === '') {
            $html = $this->templatLokal($dokumen, $statistik);
        }

        $dokumen->forceFill([
            'isi_html' => $html,
            'ringkasan_data' => $statistik,
            'dibantu_ai' => $pakaiAi,
            'status' => $dokumen->status === 'final' ? 'final' : 'draf',
        ])->save();

        return $html;
    }

    protected function bangunPrompt(AkreditasiDokumen $dokumen, string $konteksJson): string
    {
        $jenisPanduan = match ($dokumen->kode) {
            'dw_1', 'butir_09' => 'Panduan Pengembangan Kurikulum Satuan Pendidikan (KSP / KOM MTs) meliputi Bab I Karakteristik, Bab II Visi Misi Tujuan, Bab III Pengorganisasian Pembelajaran (Intrakurikuler, P5-PPRA, Ekstrakurikuler), Bab IV Perencanaan Pembelajaran, Bab V Pendampingan & Evaluasi.',
            'dw_5', 'butir_03', 'butir_04' => 'Panduan Pembelajaran dan Asesmen (PPA) serta Panduan Pengembangan P5-PPRA Fase D Madrasah Tsanawiyah.',
            default => 'Instrumen Akreditasi Satuan Pendidikan (IASP) BAN-PDM 2024 untuk jenjang Madrasah Tsanawiyah.',
        };

        return "Susun draf lengkap dan siap cetak untuk dokumen akreditasi berikut:\n"
            ."Judul Dokumen: {$dokumen->judul}\n"
            ."Kode: {$dokumen->kode} (Kategori: {$dokumen->kategori})\n"
            ."Acuan Resmi: {$jenisPanduan}\n\n"
            ."Data Nyata Madrasah (JSON):\n{$konteksJson}\n\n"
            .'Aturan: Tulis dalam Bahasa Indonesia formal baku, langsung isi dokumen HTML tanpa pembuka/penutup percakapan, '
            .'sertakan tabel ringkas jika relevan dengan data di JSON, dan gunakan angka nyata dari JSON di atas.';
    }

    protected function bersihkanHtml(string $teks): string
    {
        $teks = trim($teks);
        $teks = preg_replace('/^```html\s*/i', '', $teks) ?? $teks;
        $teks = preg_replace('/\s*```$/', '', $teks) ?? $teks;

        return trim($teks);
    }

    /**
     * @param  array<string, mixed>  $statistik
     */
    protected function templatLokal(AkreditasiDokumen $dokumen, array $statistik): string
    {
        $madrasah = e((string) ($statistik['madrasah'] ?? 'Madrasah Tsanawiyah'));
        $tahun = e((string) ($statistik['tahun_ajaran'] ?? ''));
        $siswa = (int) ($statistik['jumlah_siswa_aktif'] ?? 0);
        $rombel = (int) ($statistik['jumlah_rombel_aktif'] ?? 0);
        $gtk = (int) ($statistik['jumlah_gtk_aktif'] ?? 0);
        $perangkat = (int) ($statistik['jumlah_perangkat_ajar'] ?? 0);
        $jurnal = (int) ($statistik['jumlah_jurnal_mengajar'] ?? 0);

        return "<h2>{$dokumen->judul}</h2>"
            ."<p><strong>Satuan Pendidikan:</strong> {$madrasah} · <strong>Tahun Ajaran:</strong> {$tahun}</p>"
            .'<h3>1. Ringkasan Kondisi Nyata Madrasah</h3>'
            .'<ul>'
            ."<li>Jumlah Peserta Didik Aktif: <strong>{$siswa} siswa</strong> dalam <strong>{$rombel} rombongan belajar</strong>.</li>"
            ."<li>Jumlah Pendidik & Tenaga Kependidikan (GTK) Aktif: <strong>{$gtk} orang</strong>.</li>"
            ."<li>Perangkat Ajar Tersusun: <strong>{$perangkat} dokumen</strong> · Jurnal Mengajar Tercatat: <strong>{$jurnal} entri</strong>.</li>"
            .'</ul>'
            .'<h3>2. Deskripsi Kinerja & Implementasi</h3>'
            ."<p>Dokumen ini disusun sebagai bagian dari pemenuhan standar BAN-PDM pada {$madrasah} untuk tahun ajaran {$tahun}. "
            .'Silakan lengkapi narasi spesifik atau jalankan ulang dengan Gemini AI aktif untuk memperkaya uraian naratif.</p>';
    }
}
