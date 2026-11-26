<?php

namespace App\Services\Akreditasi;

use App\Models\EdmIndikator;
use App\Services\Gemini\KlienGemini;
use Throwable;

class PenyusunEdmAi
{
    public function __construct(
        protected ?KlienGemini $klien = null,
    ) {
        $this->klien ??= app(KlienGemini::class);
    }

    /**
     * Susun rekomendasi narasi kondisi nyata, tindak lanjut, dan saran skor (1-4) untuk 1 butir EDM.
     *
     * @return array{skor: int, kondisi_nyata: string, tindak_lanjut: string, dibantu_ai: bool}
     */
    public function sarankanIndikator(EdmIndikator $indikator, string $catatanGuru = ''): array
    {
        $definisi = KatalogEdm2026::cari($indikator->kode);
        $metrikJson = json_encode($indikator->metrik_sistem ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';
        $skorDefault = $indikator->skor_final ?? $indikator->skor_saran ?? 3;

        if (KlienGemini::aktif()) {
            try {
                $rubrik = '';
                foreach ($definisi['tingkat'] ?? [] as $lvl => $ket) {
                    $rubrik .= "Tingkat {$lvl}: {$ket}\n";
                }

                $prompt = "Indikator EDM: {$indikator->kode} — {$indikator->pertanyaan}\n\n"
                    ."Rubrik Penilaian:\n{$rubrik}\n"
                    ."Data Nyata dari Sistem Aksara (JSON):\n{$metrikJson}\n\n"
                    .'Catatan tambahan guru/TPM: '.($catatanGuru !== '' ? $catatanGuru : '(tidak ada catatan tambahan)')."\n\n"
                    ."Balas HANYA dalam format JSON valid tanpa markdown:\n"
                    .'{"skor": 1..4, "kondisi_nyata": "2-3 kalimat formal kondisi nyata berbasis data di atas", "tindak_lanjut": "1-2 kalimat rencana tindak lanjut perbaikan/e-RKAM"}';

                $respons = $this->klien->hasilkan(
                    'Anda adalah asisten Tim Penjamin Mutu (TPM) Madrasah Tsanawiyah yang membantu guru menyusun Evaluasi Diri Madrasah (EDM 2026) dengan bahasa sederhana, jelas, dan jujur berdasarkan data.',
                    $prompt,
                );

                if (filled($respons)) {
                    $jsonTeks = trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $respons));
                    $decoded = json_decode($jsonTeks, true);
                    if (is_array($decoded) && isset($decoded['kondisi_nyata'])) {
                        $skor = (int) ($decoded['skor'] ?? $skorDefault);
                        $skor = max(1, min(4, $skor));

                        return [
                            'skor' => $skor,
                            'kondisi_nyata' => trim((string) $decoded['kondisi_nyata']),
                            'tindak_lanjut' => trim((string) ($decoded['tindak_lanjut'] ?? '')),
                            'dibantu_ai' => true,
                        ];
                    }
                }
            } catch (Throwable) {
                // Fallback ke templat lokal di bawah.
            }
        }

        $ringkasan = (string) ($indikator->metrik_sistem['ringkasan'] ?? 'Data pendukung telah dihimpun oleh Tim Penjamin Mutu Madrasah.');
        $kondisi = $catatanGuru !== ''
            ? "{$ringkasan} Catatan lapangan: {$catatanGuru}."
            : $ringkasan;

        $tindakLanjut = $skorDefault >= 4
            ? 'Pertahankan capaian dan lakukan pemantauan berkala setiap semester beserta pengarsipan bukti otentik.'
            : 'Tingkatkan pendampingan, lengkapi bukti fisik/foto kegiatan, dan masukkan program peningkatan mutu terkait ke dalam e-RKAM.';

        return [
            'skor' => $skorDefault,
            'kondisi_nyata' => $kondisi,
            'tindak_lanjut' => $tindakLanjut,
            'dibantu_ai' => false,
        ];
    }
}
