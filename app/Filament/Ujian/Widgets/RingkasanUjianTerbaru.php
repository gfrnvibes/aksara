<?php

namespace App\Filament\Ujian\Widgets;

use App\Filament\Resources\Kelas\KelasResource;
use App\Filament\Ujian\Resources\Ujians\UjianResource;
use App\Models\Ujian;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RingkasanUjianTerbaru extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Ujian Terbaru & Analisis Nilai';

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                UjianResource::getEloquentQuery()
                    ->with('mataPelajaran')
                    ->withCount('butir')
                    ->withCount([
                        'peserta as peserta_dinilai_count' => fn (Builder $query): Builder => $query->whereNotNull('nilai'),
                    ])
                    ->withAvg([
                        'peserta as rata_rata_nilai' => fn (Builder $query): Builder => $query->whereNotNull('nilai'),
                    ], 'nilai')
                    ->latest('tanggal')
                    ->latest('id')
            )
            ->columns([
                TextColumn::make('nama')
                    ->label('Nama Ujian')
                    ->description(fn (Ujian $record): string => $record->mataPelajaran->nama)
                    ->weight('medium')
                    ->searchable(),
                TextColumn::make('tingkat')
                    ->label('Kelas')
                    ->formatStateUsing(fn (?int $state): string => $state !== null ? 'Kelas '.(KelasResource::getTingkatOptions()[$state] ?? $state) : '—')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->placeholder('Belum dijadwalkan'),
                TextColumn::make('butir_count')
                    ->label('Butir Soal')
                    ->numeric()
                    ->suffix(' soal')
                    ->alignCenter(),
                TextColumn::make('peserta_dinilai_count')
                    ->label('Dinilai')
                    ->numeric()
                    ->suffix(' siswa')
                    ->alignCenter(),
                TextColumn::make('rata_rata_nilai')
                    ->label('Rata-rata')
                    ->formatStateUsing(fn (mixed $state): string => $state !== null ? number_format((float) $state, 1) : '—')
                    ->badge()
                    ->color(fn (mixed $state): string => $state === null ? 'gray' : ((float) $state >= 75 ? 'success' : 'warning'))
                    ->alignCenter(),
            ])
            ->recordActions([
                Action::make('nilai')
                    ->label('Nilai')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->color('gray')
                    ->url(fn (Ujian $record): string => UjianResource::getUrl('nilai', ['record' => $record])),
                Action::make('analisis')
                    ->label('Analisis')
                    ->icon(Heroicon::OutlinedChartBarSquare)
                    ->color('primary')
                    ->url(fn (Ujian $record): string => UjianResource::getUrl('analisis', ['record' => $record])),
            ])
            ->defaultPaginationPageOption(5)
            ->paginated([5, 10])
            ->emptyStateHeading('Belum ada ujian')
            ->emptyStateDescription('Buat ujian baru dari bank soal untuk mulai memantau nilai dan analisis butir.');
    }
}
