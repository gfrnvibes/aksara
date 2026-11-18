<?php

namespace App\Filament\Guru\Pages;

use App\Models\User;
use App\Services\Harian\RingkasanTugasGuru;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class TugasSaya extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Tugas Administrasi';

    protected static ?string $title = 'Timeline Tugas Administrasi Guru';

    protected static ?string $slug = 'tugas-saya';

    protected string $view = 'filament.guru.pages.tugas-saya';

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->gtk !== null;
    }

    public static function getNavigationBadge(): ?string
    {
        $user = Filament::auth()->user();
        if (! $user instanceof User || $user->gtk === null) {
            return null;
        }

        $belum = app(RingkasanTugasGuru::class)->untuk($user)['belum'];

        return $belum > 0 ? (string) $belum : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
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
