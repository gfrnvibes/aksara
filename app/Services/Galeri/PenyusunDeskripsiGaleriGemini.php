<?php

namespace App\Services\Galeri;

use App\Services\Gemini\KlienGemini;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

class PenyusunDeskripsiGaleriGemini
{
    public function __construct(protected KlienGemini $klien) {}

    public function susun(
        int $userId,
        string $judul,
        string $kategori = 'Kesiswaan',
        ?string $tanggal = null,
        ?string $catatanAwal = null,
        string $gaya = 'ramah_emoji'
    ): string {
        if (! KlienGemini::aktif()) {
            throw new RuntimeException('Gemini AI belum dikonfigurasi (periksa GEMINI_API_KEY di .env).');
        }

        if (blank($judul) && blank($catatanAwal)) {
            throw new RuntimeException('Isi terlebih dahulu Judul Kegiatan atau catatan singkat agar AI dapat menyusun deskripsi.');
        }

        $limit = 'galeri-ai-user:'.$userId;
        if (RateLimiter::tooManyAttempts($limit, 15)) {
            throw new RuntimeException('Batas 15 permintaan AI per 10 menit tercapai. Coba lagi beberapa saat.');
        }
        RateLimiter::hit($limit, 600);

        $arahanGaya = match ($gaya) {
            'formal' => 'Gunakan bahasa Indonesia yang formal, rapi, lugas, dan profesional sesuai standar publikasi resmi madrasah/sekolah. Jangan gunakan emoji.',
            'singkat' => 'Gunakan bahasa Indonesia yang singkat, padat, ramah, dan langsung pada inti kegiatan (1–2 kalimat). Boleh gunakan 1–2 emoji relevan di awal/akhir.',
            default => 'Gunakan bahasa Indonesia yang ramah, hangat, inspiratif, dan rapi untuk publikasi galeri madrasah. Tambahkan beberapa emoji/ikon yang relevan dan proporsional (3–5 emoji) agar menarik dibaca oleh siswa, wali murid, dan masyarakat.',
        };

        $instruksi = 'Anda adalah admin publikasi dan humas madrasah yang mahir menulis deskripsi dokumentasi kegiatan siswa. '
            .'Buatlah deskripsi singkat yang menarik untuk ditampilkan di galeri website madrasah berdasarkan data kegiatan dan catatan awal pengguna. '
            .$arahanGaya.' '
            .'Panjang maksimal 2–4 kalimat (maksimal 500 karakter). Jangan mengarang fakta atau nama tokoh yang tidak disebutkan pada input. '
            .'Kembalikan hanya teks deskripsi final tanpa tanda kutip pembuka/penutup dan tanpa format markdown.';

        $isi = json_encode(array_filter([
            'judul_kegiatan' => $judul,
            'kategori' => $kategori,
            'tanggal_kegiatan' => $tanggal,
            'catatan_atau_draf_pengguna' => $catatanAwal,
        ]), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $hasil = trim(strip_tags($this->klien->hasilkan(
            $instruksi,
            $isi,
            ['temperature' => 0.7, 'maxOutputTokens' => 512],
            max(30, (int) config('services.gemini.timeout', 30)),
        )));

        if ($hasil === '') {
            throw new RuntimeException('AI tidak mengembalikan teks deskripsi. Silakan coba lagi.');
        }

        return mb_substr($hasil, 0, 2000);
    }
}
