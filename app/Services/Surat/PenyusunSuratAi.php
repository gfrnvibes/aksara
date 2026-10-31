<?php

namespace App\Services\Surat;

use App\Enums\JenisSuratKeluar;
use App\Enums\SifatSurat;
use App\Services\Gemini\KlienGemini;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

/**
 * Drafts or revises an official letter with Google Gemini from a plain-language request. Only the request, the
 * current draft and the madrasah's public details are sent; any student is referred to by placeholder.
 */
class PenyusunSuratAi
{
    /**
     * HTML tags the letter body may use; anything else Gemini writes is dropped.
     */
    public const string TAG_DIIZINKAN = '<p><br><strong><b><em><i><u><ol><ul><li><table><thead><tbody><tr><th><td>';

    protected const string INSTRUKSI = <<<'TEKS'
        Anda adalah staf tata usaha sebuah Madrasah Tsanawiyah di Indonesia yang menyusun surat dinas resmi.
        Susun surat berdasarkan "perintah". Bila "draf_sebelumnya" terisi, revisi draf itu sesuai perintah.

        Aturan:
        - Bahasa Indonesia baku dan santun sesuai tata naskah dinas madrasah.
        - "isi" hanya badan surat: salam pembuka (Assalamu'alaikum Warahmatullahi Wabarakatuh), paragraf pembuka,
          inti, paragraf penutup, dan salam penutup (Wassalamu'alaikum Warahmatullahi Wabarakatuh).
        - Jangan menulis kop, nomor, tanggal surat, lampiran, perihal, alamat tujuan, maupun tanda tangan di "isi".
        - "isi" berupa HTML yang hanya memakai tag <p>, <br>, <strong>, <em>, <ol>, <ul>, <li>, <table>, <tr>, <td>.
          Untuk rincian acara (hari/tanggal, waktu, tempat, acara) gunakan tabel dua kolom tanpa judul kolom.
        - Jangan mengarang nama orang. Bila surat menyangkut siswa tertentu, tulis placeholder persis seperti ini:
          {nama_siswa}, {nis}, {nisn}, {kelas}, {jenis_kelamin}, {tempat_tanggal_lahir}, {nama_orang_tua}.
          Nama madrasah boleh ditulis {nama_madrasah}.
        - Bila tanggal, waktu, atau tempat tidak disebut di perintah, tulis titik-titik (........) agar diisi manual.
        - "jenis" salah satu dari: undangan, pemberitahuan, keterangan, tugas, permohonan, edaran, keputusan,
          rekomendasi, lainnya. "sifat" salah satu dari: biasa, penting, segera, rahasia.
        - "tujuan" berisi penerima surat, misalnya "Bapak/Ibu Orang Tua/Wali Siswa Kelas IX".
        - "lampiran" berisi jumlah lampiran, atau "-" bila tidak ada.
        TEKS;

    public function __construct(protected KlienGemini $klien) {}

    /**
     * @param  array{nama_madrasah: string, wilayah: string|null, tanggal_hari_ini: string, jabatan_penanda_tangan: string|null}  $konteks
     * @return array{jenis: JenisSuratKeluar|null, sifat: SifatSurat|null, perihal: string, tujuan: string, lampiran: string|null, isi: string}
     *
     * @throws ConnectionException
     * @throws RequestException
     * @throws RuntimeException when Gemini blocks the request or returns no usable letter.
     */
    public function susun(string $perintah, array $konteks, ?string $drafSebelumnya = null): array
    {
        $teks = $this->klien->hasilkan(
            static::INSTRUKSI,
            (string) json_encode([
                'perintah' => $perintah,
                'konteks' => $konteks,
                'draf_sebelumnya' => filled(strip_tags((string) $drafSebelumnya)) ? $drafSebelumnya : null,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            [
                'maxOutputTokens' => 4096,
                'responseMimeType' => 'application/json',
                'responseSchema' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'jenis' => ['type' => 'STRING'],
                        'sifat' => ['type' => 'STRING'],
                        'perihal' => ['type' => 'STRING'],
                        'tujuan' => ['type' => 'STRING'],
                        'lampiran' => ['type' => 'STRING'],
                        'isi' => ['type' => 'STRING'],
                    ],
                    'required' => ['perihal', 'tujuan', 'isi'],
                ],
            ],
            max(30, (int) config('services.gemini.timeout', 15)),
        );

        $surat = json_decode($teks, true);

        if (! is_array($surat) || blank($surat['isi'] ?? null) || blank($surat['perihal'] ?? null)) {
            throw new RuntimeException('Gemini tidak mengembalikan surat yang lengkap.');
        }

        $lampiran = trim((string) ($surat['lampiran'] ?? ''));

        return [
            'jenis' => JenisSuratKeluar::tryFrom(strtolower(trim((string) ($surat['jenis'] ?? '')))),
            'sifat' => SifatSurat::tryFrom(strtolower(trim((string) ($surat['sifat'] ?? '')))),
            'perihal' => trim((string) $surat['perihal']),
            'tujuan' => trim((string) ($surat['tujuan'] ?? '')),
            'lampiran' => in_array($lampiran, ['', '-'], true) ? null : $lampiran,
            'isi' => trim(strip_tags((string) $surat['isi'], static::TAG_DIIZINKAN)),
        ];
    }
}
