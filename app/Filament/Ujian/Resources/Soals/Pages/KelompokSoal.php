<?php

namespace App\Filament\Ujian\Resources\Soals\Pages;

use App\Filament\Resources\Kelas\KelasResource;
use App\Filament\Ujian\Resources\Soals\SoalResource;
use App\Models\MataPelajaran;
use Filament\Resources\Pages\Page;

/**
 * The bank's front page: one card per subject the user works with, showing how many questions it holds in each
 * grade. Opening a card lists the subject's questions, a tab per grade.
 */
class KelompokSoal extends Page
{
    protected static string $resource = SoalResource::class;

    protected string $view = 'filament.ujian.kelompok-soal';

    /**
     * Card colours, written out in full so Tailwind generates them. A subject keeps its colour by id.
     *
     * @var list<string>
     */
    protected const array WARNA = [
        '[--ikon:var(--color-sky-600)] [--latar:var(--color-sky-50)]',
        '[--ikon:var(--color-emerald-600)] [--latar:var(--color-emerald-50)]',
        '[--ikon:var(--color-amber-600)] [--latar:var(--color-amber-50)]',
        '[--ikon:var(--color-rose-600)] [--latar:var(--color-rose-50)]',
        '[--ikon:var(--color-violet-600)] [--latar:var(--color-violet-50)]',
        '[--ikon:var(--color-teal-600)] [--latar:var(--color-teal-50)]',
        '[--ikon:var(--color-orange-600)] [--latar:var(--color-orange-50)]',
        '[--ikon:var(--color-indigo-600)] [--latar:var(--color-indigo-50)]',
    ];

    /**
     * An icon for a subject, picked from words in its name.
     *
     * @var array<string, string>
     */
    protected const array IKON = [
        'matematika' => 'heroicon-o-calculator',
        'ipa' => 'heroicon-o-beaker',
        'alam' => 'heroicon-o-beaker',
        'ips' => 'heroicon-o-globe-asia-australia',
        'sosial' => 'heroicon-o-globe-asia-australia',
        'arab' => 'heroicon-o-language',
        'inggris' => 'heroicon-o-language',
        'sunda' => 'heroicon-o-chat-bubble-left-right',
        'indonesia' => 'heroicon-o-chat-bubble-left-right',
        "qur'an" => 'heroicon-o-book-open',
        'quran' => 'heroicon-o-book-open',
        'hadis' => 'heroicon-o-book-open',
        'tahfidz' => 'heroicon-o-book-open',
        'akidah' => 'heroicon-o-sparkles',
        'aqidah' => 'heroicon-o-sparkles',
        'fikih' => 'heroicon-o-scale',
        'fiqih' => 'heroicon-o-scale',
        'sejarah' => 'heroicon-o-building-library',
        'ski' => 'heroicon-o-building-library',
        'pancasila' => 'heroicon-o-flag',
        'pkn' => 'heroicon-o-flag',
        'jasmani' => 'heroicon-o-trophy',
        'pjok' => 'heroicon-o-trophy',
        'seni' => 'heroicon-o-paint-brush',
        'prakarya' => 'heroicon-o-wrench-screwdriver',
        'informatika' => 'heroicon-o-computer-desktop',
        'tik' => 'heroicon-o-computer-desktop',
    ];

    public static function canAccess(array $parameters = []): bool
    {
        return SoalResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Bank Soal';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    /**
     * One card per subject, with its count in each grade the user works with.
     *
     * @return list<array{nama: string, total: int, siap: int, draf: int, dipakai: int, kelas: array<string, int>, url: string, ikon: string, warna: string}>
     */
    public function getKartu(): array
    {
        $jumlah = SoalResource::getEloquentQuery()
            ->toBase()
            ->select(['mata_pelajaran_id', 'tingkat'])
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when is_siap then 1 else 0 end) as siap')
            ->selectRaw('sum(case when exists (select 1 from butir_ujians where butir_ujians.soal_id = soals.id) then 1 else 0 end) as dipakai')
            ->groupBy('mata_pelajaran_id', 'tingkat')
            ->get();

        // Grades per subject: those the user teaches, plus any that already hold questions.
        $tingkatMapel = collect(SoalResource::pasanganMapelKelas())
            ->merge($jumlah->map(fn (object $baris): array => [(int) $baris->mata_pelajaran_id, (int) $baris->tingkat]))
            ->groupBy(fn (array $p): int => $p[0])
            ->map(fn ($pasangan) => $pasangan->pluck(1)->unique());

        $tingkatLabel = KelasResource::getTingkatOptions();
        $kartu = [];

        foreach (MataPelajaran::query()->whereIn('id', $tingkatMapel->keys())->aktifBerurutan()->pluck('nama', 'id') as $mapelId => $nama) {
            $milik = $jumlah->where('mata_pelajaran_id', $mapelId);
            $total = (int) $milik->sum('total');
            $siap = (int) $milik->sum('siap');
            $kelas = [];

            foreach ($tingkatLabel as $kode => $label) {
                if ($tingkatMapel[$mapelId]->contains($kode)) {
                    $kelas[$label] = (int) ($milik->firstWhere('tingkat', $kode)->total ?? 0);
                }
            }

            $kartu[] = [
                'nama' => $nama,
                'total' => $total,
                'siap' => $siap,
                'draf' => $total - $siap,
                'dipakai' => (int) $milik->sum('dipakai'),
                'kelas' => $kelas,
                'url' => SoalResource::urlKelompok($mapelId),
                'ikon' => static::ikon($nama),
                'warna' => static::WARNA[$mapelId % count(static::WARNA)],
            ];
        }

        return $kartu;
    }

    protected static function ikon(string $namaMapel): string
    {
        $kata = preg_split('/[^a-z\']+/', strtolower($namaMapel)) ?: [];

        foreach (static::IKON as $kunci => $ikon) {
            if (in_array($kunci, $kata, true)) {
                return $ikon;
            }
        }

        return 'heroicon-o-academic-cap';
    }
}
