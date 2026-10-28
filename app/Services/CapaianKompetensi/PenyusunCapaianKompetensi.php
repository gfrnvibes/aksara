<?php

namespace App\Services\CapaianKompetensi;

use App\Services\Gemini\KlienGemini;
use Throwable;

/**
 * Drafts the competence description of many grades at once. Grades of the same subject and mastery level share
 * one description, so a whole class needs at most four; Gemini writes them when an API key is configured, the
 * rules otherwise or for whatever Gemini leaves out.
 */
class PenyusunCapaianKompetensi
{
    public const string SUMBER_GEMINI = 'gemini';

    public const string SUMBER_ATURAN = 'aturan';

    public function __construct(
        protected CapaianGemini $gemini,
        protected CapaianBerbasisAturan $aturan,
    ) {}

    /**
     * @param  array<array-key, array{mata_pelajaran: string, nilai: int, kktp: int}>  $daftarNilai  keyed by the caller's row key
     * @param  array{kelas: string|null, fase: string|null, semester: string|null}  $konteks
     * @return array{capaian: array<array-key, string>, sumber: string, alasanGagalAi: string|null}
     */
    public function susun(array $daftarNilai, array $konteks, ?string $materi = null): array
    {
        $kelompok = [];

        foreach ($daftarNilai as $kunciBaris => $nilai) {
            $awalan = CapaianBerbasisAturan::awalan($nilai['nilai'], $nilai['kktp']);
            $kunci = md5($nilai['mata_pelajaran'].'|'.$awalan);

            $kelompok[$kunci] ??= [
                'permintaan' => ['kunci' => $kunci, 'mata_pelajaran' => $nilai['mata_pelajaran'], 'awalan' => $awalan, 'materi' => $materi],
                'aturan' => $this->aturan->deskripsi($nilai['mata_pelajaran'], $nilai['nilai'], $nilai['kktp'], $materi),
                'baris' => [],
            ];
            $kelompok[$kunci]['baris'][] = $kunciBaris;
        }

        $dariGemini = [];
        $sumber = static::SUMBER_ATURAN;
        $alasanGagalAi = null;

        if ($kelompok !== [] && KlienGemini::aktif()) {
            try {
                $dariGemini = $this->gemini->buat(array_column($kelompok, 'permintaan'), $konteks);
                $sumber = static::SUMBER_GEMINI;
            } catch (Throwable $exception) {
                report($exception);
                $alasanGagalAi = KlienGemini::alasanGagal($exception);
            }
        }

        $capaian = [];

        foreach ($kelompok as $kunci => $isi) {
            foreach ($isi['baris'] as $kunciBaris) {
                $capaian[$kunciBaris] = $dariGemini[$kunci] ?? $isi['aturan'];
            }
        }

        return ['capaian' => $capaian, 'sumber' => $sumber, 'alasanGagalAi' => $alasanGagalAi];
    }
}
