<?php

namespace App\Filament\Guru\Widgets;

use App\Enums\Semester;
use App\Filament\Guru\Pages\InputNilai;
use App\Models\NilaiRapor;
use App\Models\PembagianMengajar;
use App\Models\TahunAjaran;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The subjects the teacher teaches this academic year, with how many students already have this semester's
 * grade, and a shortcut to the grade sheet of each.
 */
class TugasMengajarSaya extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $user = Filament::auth()->user();
        $tahunAjaran = TahunAjaran::query()->where('is_aktif', true)->first();
        $semester = Semester::berdasarkanTanggal(now());

        return $table
            ->heading('Tugas Mengajar Saya')
            ->description($tahunAjaran === null
                ? 'Belum ada tahun ajaran aktif.'
                : "Tahun ajaran {$tahunAjaran->nama}, {$semester->getLabel()}. Tekan Input Nilai untuk mengisi nilai kelas tersebut.")
            ->query(fn (): Builder => PembagianMengajar::query()
                ->milik($user instanceof User ? $user->gtk : null, $tahunAjaran->id ?? 0)
                ->with(['kelas', 'mataPelajaran'])
                ->select('pembagian_mengajars.*')
                ->selectSub(NilaiRapor::query()
                    ->selectRaw('count(*)')
                    ->join('rapors', 'rapors.id', '=', 'nilai_rapors.rapor_id')
                    ->whereColumn('rapors.kelas_id', 'pembagian_mengajars.kelas_id')
                    ->whereColumn('rapors.tahun_ajaran_id', 'pembagian_mengajars.tahun_ajaran_id')
                    ->whereColumn('nilai_rapors.mata_pelajaran_id', 'pembagian_mengajars.mata_pelajaran_id')
                    ->where('rapors.semester', $semester)
                    ->whereNull('rapors.deleted_at'), 'jumlah_dinilai')
                ->join('kelas', 'kelas.id', '=', 'pembagian_mengajars.kelas_id')
                ->orderBy('kelas.tingkat')
                ->orderBy('kelas.nama'))
            ->paginated(false)
            ->emptyStateHeading('Belum ada tugas mengajar')
            ->emptyStateDescription('Hubungi operator untuk mengisi pembagian mengajar Anda tahun ini.')
            ->columns([
                TextColumn::make('kelas.nama')
                    ->label('Kelas')
                    ->weight('bold'),
                TextColumn::make('mataPelajaran.nama')
                    ->label('Mata Pelajaran'),
                TextColumn::make('jp_per_minggu')
                    ->label('JP / Minggu'),
                TextColumn::make('jumlah_dinilai')
                    ->label('Siswa Sudah Dinilai'),
            ])
            ->recordActions([
                Action::make('inputNilai')
                    ->label('Input Nilai')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->button()
                    ->url(fn (PembagianMengajar $record): string => InputNilai::getUrl(['kelas' => $record->kelas_id, 'mapel' => $record->mata_pelajaran_id])),
            ]);
    }
}
