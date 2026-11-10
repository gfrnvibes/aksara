<?php

namespace App\Filament\Guru\Widgets;

use App\Filament\Guru\Pages\JurnalMengajar;
use App\Models\JurnalMengajar as Jurnal;
use App\Models\User;
use App\Services\Harian\AksesHarian;
use App\Services\Harian\PertemuanMengajar;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class JadwalHariIni extends TableWidget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    public function table(Table $table): Table
    {
        $user = Filament::auth()->user();

        return $table->heading('Jadwal Mengajar Hari Ini')->description(now('Asia/Jakarta')->translatedFormat('l, j F Y'))
            ->records(fn () => $user instanceof User ? app(PertemuanMengajar::class)->daftar($user, AksesHarian::hariIni()) : collect())
            ->paginated(false)->emptyStateHeading('Tidak ada jam mengajar hari ini')->emptyStateIcon('heroicon-o-calendar-days')
            ->columns([
                TextColumn::make('jp_mulai')->label('Jam')->formatStateUsing(fn (array $record): string => 'JP '.$record['jp_mulai'].'–'.$record['jp_selesai'])->description(fn (array $record): ?string => $record['waktu']),
                TextColumn::make('nama_kelas')->label('Kelas')->weight('bold'),
                TextColumn::make('nama_mapel')->label('Mata pelajaran'),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state): string => Jurnal::STATUS[$state] ?? 'Belum dicatat')->color(fn (string $state): string => $state === 'terlaksana' ? 'success' : 'warning'),
            ])->recordActions([
                Action::make('hadir')->label('Hadir mengajar')->icon('heroicon-o-check')->visible(fn (array $record): bool => $record['status'] === 'belum_dicatat')
                    ->action(function (array $record) use ($user): void {
                        abort_unless($user instanceof User, 403);
                        app(PertemuanMengajar::class)->simpan($user, AksesHarian::hariIni(), $record['sumber_jadwal'], [], true);
                        Notification::make()->success()->title('Kehadiran tercatat; lengkapi jurnal setelah mengajar')->send();
                    }),
                Action::make('jurnal')->label('Isi jurnal')->icon('heroicon-o-pencil-square')->url(JurnalMengajar::getUrl()),
            ]);
    }
}
