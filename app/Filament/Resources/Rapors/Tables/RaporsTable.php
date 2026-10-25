<?php

namespace App\Filament\Resources\Rapors\Tables;

use App\Enums\Semester;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RaporsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['siswa', 'tahunAjaran', 'kelas'])
                ->withAvg('nilaiRapor', 'nilai_pengetahuan'))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('siswa.nis')
                    ->label('NIS')
                    ->searchable(),
                TextColumn::make('siswa.nama_lengkap')
                    ->label('Nama Siswa')
                    ->searchable(),
                TextColumn::make('tahunAjaran.nama')
                    ->label('Tahun Ajaran'),
                TextColumn::make('semester')
                    ->sortable(),
                TextColumn::make('kelas.nama')
                    ->label('Kelas'),
                TextColumn::make('nilai_rapor_avg_nilai_pengetahuan')
                    ->label('Rata-rata')
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('keputusan')
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('tahun_ajaran')
                    ->label('Tahun Ajaran')
                    ->relationship('tahunAjaran', 'nama')
                    ->preload(),
                SelectFilter::make('semester')
                    ->options(Semester::class),
                SelectFilter::make('kelas')
                    ->relationship('kelas', 'nama')
                    ->preload(),
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
