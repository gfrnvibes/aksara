<?php

namespace App\Filament\Guru\Widgets;

use App\Models\User;
use App\Services\Harian\RingkasanTugasGuru;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * Compact summary card on the Guru dashboard linking to the full TugasSaya timeline page.
 */
class TimelineTugasGuru extends Widget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.guru.widgets.timeline-tugas-guru';

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->gtk !== null;
    }

    /**
     * @return array{
     *     total: int,
     *     selesai: int,
     *     belum: int,
     *     persen: int,
     *     items: list<array{
     *         id: string,
     *         kategori: string,
     *         judul: string,
     *         ringkasan: string,
     *         status: 'selesai'|'sebagian'|'belum',
     *         labelStatus: string,
     *         url: string,
     *         labelAksi: string,
     *         detail: array<int, array{label: string, selesai: bool}>,
     *     }>,
     * }
     */
    public function ringkasan(): array
    {
        $user = Filament::auth()->user();

        return $user instanceof User
            ? app(RingkasanTugasGuru::class)->untuk($user)
            : ['total' => 0, 'selesai' => 0, 'belum' => 0, 'persen' => 0, 'items' => []];
    }
}
