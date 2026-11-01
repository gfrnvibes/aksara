<?php

namespace App\Filament\Resources\PermintaanJadwals\Tables;

use App\Enums\JenisPermintaanJadwal;
use App\Enums\SifatPermintaanJadwal;
use App\Models\PermintaanJadwal;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PermintaanJadwalsTable
{
    public static function configure(Table $table, bool $kelompokkanPerGuru = true): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['gtk', 'mataPelajaran']))
            ->groups($kelompokkanPerGuru ? [
                Group::make('gtk.nama')
                    ->label('Guru')
                    ->collapsible(),
            ] : [])
            ->defaultGroup($kelompokkanPerGuru ? 'gtk.nama' : null)
            ->emptyStateHeading('Belum ada permintaan jadwal')
            ->emptyStateDescription('Guru tanpa permintaan dianggap siap mengajar kapan saja.')
            ->columns([
                TextColumn::make('ringkasan')
                    ->label('Permintaan')
                    ->state(fn (PermintaanJadwal $record): string => $record->ringkasan())
                    ->description(fn (PermintaanJadwal $record): ?string => $record->keterangan)
                    ->wrap(),
                TextColumn::make('sifat')
                    ->badge(),
                ToggleColumn::make('is_aktif')
                    ->label('Berlaku'),
            ])
            ->filters([
                SelectFilter::make('jenis')
                    ->options(JenisPermintaanJadwal::class),
                SelectFilter::make('sifat')
                    ->options(SifatPermintaanJadwal::class),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
