<?php

namespace App\Filament\Widgets;

use App\Enums\JenisKelamin;
use App\Enums\Semester;
use App\Enums\StatusSiswa;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Active students of each active class by gender, with how many of them already have this semester's report card.
 */
class SiswaPerKelas extends TableWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Filament::auth()->user()?->can('viewAny', Siswa::class) ?? false;
    }

    public function table(Table $table): Table
    {
        $tahunAjaranId = TahunAjaran::query()->where('is_aktif', true)->value('id');
        $semester = Semester::berdasarkanTanggal(now());
        $aktif = fn (Builder $query): Builder => $query->where('status', StatusSiswa::Aktif);

        return $table
            ->heading('Siswa per Kelas')
            ->description("Rapor dihitung untuk {$semester->getLabel()} tahun ajaran aktif.")
            ->query(fn (): Builder => Kelas::query()
                ->aktif()
                ->withCount([
                    'siswa as jumlah_siswa' => $aktif,
                    'siswa as jumlah_laki_laki' => fn (Builder $query) => $aktif($query)->where('jenis_kelamin', JenisKelamin::LakiLaki),
                    'siswa as jumlah_perempuan' => fn (Builder $query) => $aktif($query)->where('jenis_kelamin', JenisKelamin::Perempuan),
                    'siswa as jumlah_rapor' => fn (Builder $query) => $aktif($query)->whereHas('rapor', fn (Builder $rapor) => $rapor
                        ->where('tahun_ajaran_id', $tahunAjaranId)
                        ->where('semester', $semester)),
                ])
                ->orderBy('tingkat')
                ->orderBy('nama'))
            ->paginated(false)
            ->columns([
                TextColumn::make('nama')
                    ->label('Kelas'),
                TextColumn::make('wali_kelas')
                    ->label('Wali Kelas')
                    ->placeholder('Belum diisi'),
                TextColumn::make('jumlah_laki_laki')
                    ->label('L')
                    ->alignEnd(),
                TextColumn::make('jumlah_perempuan')
                    ->label('P')
                    ->alignEnd(),
                TextColumn::make('jumlah_siswa')
                    ->label('Jumlah')
                    ->weight('bold')
                    ->alignEnd(),
                TextColumn::make('jumlah_rapor')
                    ->label('Rapor Semester Ini')
                    ->formatStateUsing(fn (int $state, Kelas $record): string => "{$state} / {$record->getAttribute('jumlah_siswa')}")
                    ->badge()
                    ->color(fn (int $state, Kelas $record): string => $state >= (int) $record->getAttribute('jumlah_siswa') ? 'success' : 'warning')
                    ->alignEnd(),
            ]);
    }
}
