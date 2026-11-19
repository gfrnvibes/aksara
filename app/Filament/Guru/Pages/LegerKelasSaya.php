<?php

namespace App\Filament\Guru\Pages;

use App\Filament\Pages\LegerNilai;
use App\Models\Gtk;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Class grade recap in the teacher panel, restricted to the homeroom teacher's own class(es).
 */
class LegerKelasSaya extends LegerNilai
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Leger Kelas Saya';

    protected static ?string $title = 'Leger Kelas Saya';

    protected static ?string $slug = 'leger-kelas-saya';

    public static function canAccess(): bool
    {
        return static::gtk()?->kelasPerwalian()->exists() ?? false;
    }

    protected static function gtk(): ?Gtk
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user->gtk : null;
    }

    /**
     * @return array<int, string>
     */
    protected static function opsiKelas(): array
    {
        return static::gtk()?->kelasPerwalian()
            ->orderBy('tingkat')
            ->orderBy('nama')
            ->pluck('nama', 'id')
            ->all() ?? [];
    }

    protected static function defaultKelasId(): ?int
    {
        return array_key_first(static::opsiKelas());
    }
}
