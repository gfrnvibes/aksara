<?php

namespace App\Filament\Guru\Widgets;

use App\Enums\Hari;
use App\Filament\Guru\Pages\JadwalSaya;
use App\Models\IsiJadwal;
use App\Models\User;
use App\Services\Jadwal\PolaJam;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The teacher's lessons today, from the published timetable.
 */
class JadwalHariIni extends TableWidget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $user = Filament::auth()->user();
        $gtk = $user instanceof User ? $user->gtk : null;
        $jadwal = JadwalSaya::jadwalTerbit();
        $hari = Hari::dariTanggal(now());
        $pola = PolaJam::muat();

        return $table
            ->heading('Jadwal Mengajar Hari Ini')
            ->description(now()->translatedFormat('l, j F Y'))
            ->query(fn (): Builder => IsiJadwal::query()
                ->with(['kelas', 'pembagianMengajar.mataPelajaran'])
                ->where('jadwal_id', $jadwal->id ?? 0)
                ->where('hari', $hari->value ?? 0)
                ->whereHas('pembagianMengajar', fn (Builder $query) => $query->where('gtk_id', $gtk->id ?? 0))
                ->orderBy('jp_ke'))
            ->paginated(false)
            ->emptyStateHeading($jadwal === null ? 'Jadwal semester ini belum terbit' : 'Tidak ada jam mengajar hari ini')
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->columns([
                TextColumn::make('jp_ke')
                    ->label('Jam')
                    ->formatStateUsing(fn (int $state): string => "JP {$state}")
                    ->description(fn (IsiJadwal $record): ?string => $pola->jamUntuk($record->hari, $record->jp_ke)?->rentang()),
                TextColumn::make('kelas.nama')
                    ->label('Kelas')
                    ->weight('bold'),
                TextColumn::make('pembagianMengajar.mataPelajaran.nama')
                    ->label('Mata Pelajaran'),
            ]);
    }
}
