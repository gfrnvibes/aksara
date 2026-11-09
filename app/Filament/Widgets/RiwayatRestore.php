<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\BackupData;
use App\Services\CatatanRestore;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RiwayatRestore extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return BackupData::canAccess();
    }

    public function table(Table $table): Table
    {
        return $table->heading('Riwayat Restore')
            ->description('Riwayat tetap tersedia setelah data dipulihkan.')
            ->poll('10s')->defaultPaginationPageOption(25)
            ->records(fn () => app(CatatanRestore::class)->semua())
            ->columns([
                TextColumn::make('created_at')->label('Tanggal')->dateTime('d M Y H:i'),
                TextColumn::make('actor')->label('Petugas'),
                TextColumn::make('disk')->label('Lokasi')->formatStateUsing(fn (string $state): string => BackupData::labelDisk($state)),
                TextColumn::make('path')->label('Arsip')->formatStateUsing(fn (string $state): string => basename($state))->wrap(),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state)))
                    ->color(fn (string $state): string => match ($state) {
                        'berhasil' => 'success', 'gagal' => 'danger', default => 'warning',
                    }),
                TextColumn::make('error')->label('Kesalahan')->placeholder('—')->wrap()->limit(200)
                    ->tooltip(fn (array $record): ?string => $record['error'])->color('danger'),
                TextColumn::make('safety_path')->label('Backup pengaman (lokal)')->wrap()->placeholder('—'),
            ])->emptyStateHeading('Belum ada restore');
    }
}
