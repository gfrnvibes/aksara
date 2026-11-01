<?php

namespace App\Filament\Guru\Widgets;

use App\Models\KalenderAkademik;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Holidays, exams and events of the academic calendar in the coming month.
 */
class AgendaMadrasah extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Agenda Madrasah 30 Hari ke Depan')
            ->query(fn (): Builder => KalenderAkademik::query()->mendatang(30))
            ->paginated(false)
            ->emptyStateHeading('Tidak ada agenda dalam 30 hari ke depan')
            ->emptyStateIcon('heroicon-o-calendar')
            ->columns([
                TextColumn::make('tanggal')
                    ->state(fn (KalenderAkademik $record): string => $record->rentang()),
                TextColumn::make('nama')
                    ->label('Agenda')
                    ->weight('bold')
                    ->description(fn (KalenderAkademik $record): ?string => $record->keterangan),
                TextColumn::make('jenis')
                    ->badge(),
            ]);
    }
}
