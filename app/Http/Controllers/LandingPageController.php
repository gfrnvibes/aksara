<?php

namespace App\Http\Controllers;

use App\Models\Ekstrakurikuler;
use App\Models\MataPelajaran;
use App\Models\TahunAjaran;
use Illuminate\Support\Str;
use Illuminate\View\View;
use NumberFormatter;

class LandingPageController extends Controller
{
    /**
     * Tile colours for the subject icons, cycled in report-card order.
     *
     * @var array<int, string>
     */
    protected const array WARNA = ['#2f5bd3', '#c2410c', '#7c3aed', '#0f766e', '#b45309', '#be185d', '#15803d', '#0369a1', '#4d7c0f', '#9333ea', '#dc2626', '#0891b2', '#ca8a04', '#334155'];

    /**
     * The public landing page of the madrasah.
     */
    public function __invoke(): View
    {
        $mataPelajaran = MataPelajaran::query()
            ->aktifBerurutan()
            ->get()
            ->values()
            ->map(fn (MataPelajaran $mapel, int $index): array => [
                'nama' => $mapel->nama,
                'singkatan' => static::singkatan($mapel->nama),
                'ikon' => static::ikonMataPelajaran($mapel->nama),
                'warna' => static::WARNA[$index % count(static::WARNA)],
            ]);

        return view('welcome', [
            'sekolah' => config('sekolah'),
            'mataPelajaran' => $mataPelajaran,
            'jumlahMataPelajaran' => static::eja($mataPelajaran->count()),
            'ekstrakurikuler' => Ekstrakurikuler::query()->aktif()->orderByDesc('is_wajib')->orderBy('nama')->pluck('nama'),
            'program' => static::program(),
            'nilai' => static::nilai(),
            'spmb' => TahunAjaran::pendaftaranPpdbDibuka(),
        ]);
    }

    /**
     * Curriculum areas shown in the "Program" grid.
     *
     * @return array<int, array{judul: string, aksen: string, kategori: string, deskripsi: string, label: array<int, array{teks: string, warna: string}>, ikon: string, warna: string}>
     */
    protected static function program(): array
    {
        return [
            [
                'judul' => 'Pendidikan',
                'aksen' => 'Agama',
                'kategori' => 'Rumpun PAI',
                'deskripsi' => "Al-Qur'an Hadis, Akidah Akhlak, Fikih, dan Sejarah Kebudayaan Islam sebagai inti pembelajaran madrasah.",
                'label' => [['teks' => 'Kurikulum inti', 'warna' => 'blue'], ['teks' => 'Kelas VII–IX', 'warna' => 'green']],
                'ikon' => 'heroicon-o-book-open',
                'warna' => '#2f5bd3',
            ],
            [
                'judul' => 'Bahasa',
                'aksen' => 'Arab',
                'kategori' => 'Bahasa',
                'deskripsi' => "Bekal memahami Al-Qur'an, hadis, dan khazanah keilmuan Islam dalam bahasa aslinya.",
                'label' => [['teks' => 'Ciri khas madrasah', 'warna' => 'orange'], ['teks' => 'Kelas VII–IX', 'warna' => 'green']],
                'ikon' => 'heroicon-o-language',
                'warna' => '#c2410c',
            ],
            [
                'judul' => 'Sains &',
                'aksen' => 'Matematika',
                'kategori' => 'MIPA',
                'deskripsi' => 'Matematika dan Ilmu Pengetahuan Alam untuk melatih nalar, ketelitian, dan rasa ingin tahu.',
                'label' => [['teks' => 'Kurikulum nasional', 'warna' => 'blue'], ['teks' => 'Kelas VII–IX', 'warna' => 'green']],
                'ikon' => 'heroicon-o-beaker',
                'warna' => '#7c3aed',
            ],
            [
                'judul' => 'Bahasa',
                'aksen' => '& Literasi',
                'kategori' => 'Bahasa',
                'deskripsi' => 'Bahasa Indonesia dan Bahasa Inggris untuk membaca, menulis, dan berkomunikasi dengan percaya diri.',
                'label' => [['teks' => 'Kurikulum nasional', 'warna' => 'blue'], ['teks' => 'Kelas VII–IX', 'warna' => 'green']],
                'ikon' => 'heroicon-o-chat-bubble-left-right',
                'warna' => '#0f766e',
            ],
            [
                'judul' => 'Sosial &',
                'aksen' => 'Kebangsaan',
                'kategori' => 'IPS · PPKn',
                'deskripsi' => 'Ilmu Pengetahuan Sosial dan Pendidikan Pancasila untuk memahami masyarakat dan mencintai tanah air.',
                'label' => [['teks' => 'Kurikulum nasional', 'warna' => 'blue'], ['teks' => 'Kelas VII–IX', 'warna' => 'green']],
                'ikon' => 'heroicon-o-globe-asia-australia',
                'warna' => '#b45309',
            ],
            [
                'judul' => 'Seni,',
                'aksen' => 'Olahraga & Prakarya',
                'kategori' => 'Muatan Kelompok B',
                'deskripsi' => 'Seni Budaya, Pendidikan Jasmani, dan Prakarya untuk menyalurkan kreativitas dan menjaga kebugaran.',
                'label' => [['teks' => 'Kurikulum nasional', 'warna' => 'blue'], ['teks' => 'Praktik', 'warna' => 'orange']],
                'ikon' => 'heroicon-o-paint-brush',
                'warna' => '#be185d',
            ],
            [
                'judul' => 'Ekstra',
                'aksen' => 'kurikuler',
                'kategori' => 'Pengembangan diri',
                'deskripsi' => 'Kegiatan di luar jam pelajaran untuk melatih kepemimpinan, kemandirian, dan kepedulian.',
                'label' => [['teks' => 'Pramuka wajib', 'warna' => 'green'], ['teks' => 'Pilihan', 'warna' => 'blue']],
                'ikon' => 'heroicon-o-flag',
                'warna' => '#15803d',
            ],
        ];
    }

    /**
     * The madrasah's commitments shown in the "Nilai" grid.
     *
     * @return array<int, array{judul: string, deskripsi: string, ikon: string}>
     */
    protected static function nilai(): array
    {
        return [
            ['judul' => 'Akhlak lebih dulu', 'deskripsi' => 'Ilmu yang bermanfaat berawal dari adab. Pembiasaan ibadah dan sikap santun menjadi bagian dari keseharian.', 'ikon' => 'heroicon-o-heart'],
            ['judul' => 'Ilmu agama dan umum', 'deskripsi' => 'Kurikulum madrasah memadukan mata pelajaran keagamaan dengan mata pelajaran umum secara seimbang.', 'ikon' => 'heroicon-o-scale'],
            ['judul' => 'Tercatat dengan rapi', 'deskripsi' => 'Biodata dan nilai setiap siswa tersimpan di Buku Induk digital, lengkap dari kelas VII hingga lulus.', 'ikon' => 'heroicon-o-clipboard-document-check'],
            ['judul' => 'Dekat dengan orang tua', 'deskripsi' => 'Perkembangan belajar dilaporkan setiap semester sehingga orang tua dapat ikut mendampingi.', 'ikon' => 'heroicon-o-user-group'],
            ['judul' => 'Belajar aktif', 'deskripsi' => 'Diskusi, praktik, dan proyek membuat siswa terlibat langsung, bukan sekadar mendengar.', 'ikon' => 'heroicon-o-light-bulb'],
            ['judul' => 'Data siswa terjaga', 'deskripsi' => 'Akses data dibatasi sesuai peran petugas, setiap perubahan tercatat, dan dicadangkan secara berkala.', 'ikon' => 'heroicon-o-shield-check'],
        ];
    }

    protected static function ikonMataPelajaran(string $nama): string
    {
        $nama = Str::lower($nama);

        return match (true) {
            Str::contains($nama, ['qur', 'hadis', 'hadist']) => 'heroicon-o-book-open',
            Str::contains($nama, 'akhlak') => 'heroicon-o-heart',
            Str::contains($nama, ['fiqih', 'fikih']) => 'heroicon-o-scale',
            Str::contains($nama, ['sejarah', 'kebudayaan islam']) => 'heroicon-o-building-library',
            Str::contains($nama, 'arab') => 'heroicon-o-language',
            Str::contains($nama, ['pancasila', 'kewarganegaraan']) => 'heroicon-o-flag',
            Str::contains($nama, 'indonesia') => 'heroicon-o-chat-bubble-left-right',
            Str::contains($nama, 'inggris') => 'heroicon-o-chat-bubble-oval-left',
            Str::contains($nama, 'matematika') => 'heroicon-o-calculator',
            Str::contains($nama, ['alam', 'ipa']) => 'heroicon-o-beaker',
            Str::contains($nama, ['sosial', 'ips']) => 'heroicon-o-globe-asia-australia',
            Str::contains($nama, 'seni') => 'heroicon-o-paint-brush',
            Str::contains($nama, ['jasmani', 'olahraga']) => 'heroicon-o-trophy',
            Str::contains($nama, 'prakarya') => 'heroicon-o-wrench-screwdriver',
            default => 'heroicon-o-academic-cap',
        };
    }

    /**
     * A short tile label, e.g. "Pendidikan Jasmani, Olahraga dan Kesehatan" becomes "PJOK".
     */
    protected static function singkatan(string $nama): string
    {
        $singkatanTetap = [
            'Pendidikan Pancasila dan Kewarganegaraan' => 'PPKn',
            'Ilmu Pengetahuan Alam' => 'IPA',
            'Ilmu Pengetahuan Sosial' => 'IPS',
            'Pendidikan Jasmani, Olahraga dan Kesehatan' => 'PJOK',
            'Sejarah Kebudayaan Islam' => 'SKI',
        ];

        return $singkatanTetap[$nama] ?? Str::limit($nama, 16, '…');
    }

    protected static function eja(int $angka): string
    {
        $teks = class_exists(NumberFormatter::class)
            ? (new NumberFormatter('id', NumberFormatter::SPELLOUT))->format($angka)
            : (string) $angka;

        return Str::ucfirst((string) $teks);
    }
}
