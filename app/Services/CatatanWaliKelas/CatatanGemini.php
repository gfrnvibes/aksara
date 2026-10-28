<?php

namespace App\Services\CatatanWaliKelas;

use App\Services\Gemini\KlienGemini;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

/**
 * Writes the homeroom note with Google Gemini. Only the anonymous summary is sent.
 */
class CatatanGemini implements PembuatCatatan
{
    protected const string INSTRUKSI = <<<'TEKS'
        Anda adalah wali kelas di sebuah Madrasah Tsanawiyah. Tulis catatan wali kelas untuk rapor satu semester
        berdasarkan data JSON yang diberikan.

        Aturan:
        - Bahasa Indonesia yang santun, hangat, dan memotivasi; 3 sampai 5 kalimat dalam satu paragraf.
        - Sebut siswa sebagai "Ananda" di awal kalimat pertama, lalu "ananda" bila perlu. Jangan menulis nama.
        - Hanya gunakan fakta dari data: sebutkan kekuatan, mata pelajaran yang masih di bawah KKM (bila ada),
          ekstrakurikuler, akhlak dan kepribadian, serta kehadiran bila ada yang perlu diperhatikan.
        - Jangan mengarang nilai, peringkat, atau kejadian yang tidak ada di data.
        - Bila "keputusan_akhir_tahun" terisi, tutup catatan dengan keputusan itu: ucapkan selamat untuk naik kelas
          (sebutkan kelas tujuannya bila ada) atau kelulusan. Untuk "Tinggal di Kelas", sampaikan dengan nada yang
          menguatkan dan tidak menghakimi, serta ajak ananda dan orang tua untuk bersama-sama memperbaikinya.
          Bila kosong, jangan menyebut kenaikan kelas sama sekali.
        - Tulis teks biasa tanpa judul, tanpa poin-poin, dan tanpa format markdown.
        TEKS;

    public function __construct(protected KlienGemini $klien) {}

    /**
     * @throws ConnectionException
     * @throws RequestException
     * @throws RuntimeException when Gemini blocks the request or returns no text.
     */
    public function buat(RingkasanRapor $ringkasan): string
    {
        $teks = $this->klien->hasilkan(
            static::INSTRUKSI,
            (string) json_encode($ringkasan->untukAi(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        );

        return (string) preg_replace('/\s+/', ' ', $teks);
    }
}
