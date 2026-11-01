<?php

namespace App\Filament\Guru\Pages;

use App\Filament\Pages\InputNilaiKelas;
use App\Models\Gtk;
use App\Models\PembagianMengajar;
use App\Models\TahunAjaran;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * The class grade sheet of the teacher panel, offering only the classes and subjects assigned to the teacher.
 */
class InputNilai extends InputNilaiKelas
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Input Nilai';

    protected static ?string $title = 'Input Nilai';

    protected static ?string $slug = 'input-nilai';

    public static function canAccess(): bool
    {
        return static::gtk() !== null;
    }

    protected static function gtk(): ?Gtk
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user->gtk : null;
    }

    /**
     * @return array<int, string>
     */
    protected static function opsiTahunAjaran(): array
    {
        return TahunAjaran::query()
            ->whereIn('id', PembagianMengajar::query()->milik(static::gtk())->select('tahun_ajaran_id'))
            ->orderByDesc('nama')
            ->pluck('nama', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected static function opsiKelas(?int $tahunAjaranId): array
    {
        if ($tahunAjaranId === null) {
            return [];
        }

        return PembagianMengajar::query()
            ->milik(static::gtk(), $tahunAjaranId)
            ->join('kelas', 'kelas.id', '=', 'pembagian_mengajars.kelas_id')
            ->orderBy('kelas.tingkat')
            ->orderBy('kelas.nama')
            ->pluck('kelas.nama', 'kelas.id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected static function opsiMataPelajaran(?int $tahunAjaranId, ?int $kelasId): array
    {
        if ($tahunAjaranId === null || $kelasId === null) {
            return [];
        }

        return PembagianMengajar::query()
            ->milik(static::gtk(), $tahunAjaranId)
            ->where('kelas_id', $kelasId)
            ->join('mata_pelajarans', 'mata_pelajarans.id', '=', 'pembagian_mengajars.mata_pelajaran_id')
            ->orderBy('mata_pelajarans.urutan')
            ->orderBy('mata_pelajarans.nama')
            ->pluck('mata_pelajarans.nama', 'mata_pelajarans.id')
            ->all();
    }

    protected static function bolehMengisi(int $tahunAjaranId, int $kelasId, int $mataPelajaranId): bool
    {
        return PembagianMengajar::query()
            ->milik(static::gtk(), $tahunAjaranId)
            ->where('kelas_id', $kelasId)
            ->where('mata_pelajaran_id', $mataPelajaranId)
            ->exists();
    }
}
