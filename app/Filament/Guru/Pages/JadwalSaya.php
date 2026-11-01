<?php

namespace App\Filament\Guru\Pages;

use App\Enums\Hari;
use App\Enums\Semester;
use App\Models\Gtk;
use App\Models\IsiJadwal;
use App\Models\Jadwal;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\Jadwal\PolaJam;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * The teacher's own lessons of the week, from the published timetable of the current semester.
 */
class JadwalSaya extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Jadwal Saya';

    protected static ?string $title = 'Jadwal Mengajar Saya';

    protected static ?string $slug = 'jadwal-saya';

    public static function canAccess(): bool
    {
        return static::gtk() !== null;
    }

    protected static function gtk(): ?Gtk
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user->gtk : null;
    }

    public static function jadwalTerbit(): ?Jadwal
    {
        return Jadwal::terbitUntuk(TahunAjaran::query()->where('is_aktif', true)->value('id'), Semester::berdasarkanTanggal(now()));
    }

    /**
     * The teacher's lessons of the published timetable, by day, in period order.
     *
     * @return Collection<int, Collection<int, IsiJadwal>>
     */
    public static function pelajaranPerHari(?Jadwal $jadwal, ?Gtk $gtk): Collection
    {
        if ($jadwal === null || $gtk === null) {
            return collect();
        }

        return $jadwal->isi()
            ->with(['kelas', 'pembagianMengajar.mataPelajaran'])
            ->whereHas('pembagianMengajar', fn ($query) => $query->where('gtk_id', $gtk->id))
            ->orderBy('hari')
            ->orderBy('jp_ke')
            ->get()
            ->toBase()
            ->groupBy(fn (IsiJadwal $isi): int => $isi->hari->value);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.jadwal.jadwal-saya')
                ->viewData(fn (): array => [
                    'jadwal' => $jadwal = static::jadwalTerbit(),
                    'perHari' => static::pelajaranPerHari($jadwal, static::gtk()),
                    'pola' => PolaJam::muat(),
                    'hariIni' => Hari::dariTanggal(now()),
                ]),
        ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('cetak')
                ->label('Cetak Jadwal Lengkap')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->visible(fn (): bool => static::jadwalTerbit() !== null)
                ->url(fn (): string => route('jadwal.cetak', static::jadwalTerbit()))
                ->openUrlInNewTab(),
        ];
    }
}
