<?php

namespace App\Filament\Resources\Siswas\RelationManagers;

use App\Filament\Resources\Rapors\RaporResource;
use App\Models\Rapor;
use App\Models\TahunAjaran;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RaporRelationManager extends RelationManager
{
    protected static string $relationship = 'rapor';

    protected static ?string $title = 'Data Nilai (Rapor per Semester)';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['tahunAjaran', 'kelas'])
                ->withAvg('nilaiRapor', 'nilai_pengetahuan')
                ->withAvg('nilaiRapor', 'nilai_keterampilan'))
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderBy(
                    TahunAjaran::query()->select('nama')->whereColumn('tahun_ajarans.id', 'rapors.tahun_ajaran_id'),
                )
                ->orderBy('semester'))
            ->columns([
                TextColumn::make('tahunAjaran.nama')
                    ->label('Tahun Ajaran'),
                TextColumn::make('semester'),
                TextColumn::make('kelas.nama')
                    ->label('Kelas'),
                TextColumn::make('nilai_rapor_avg_nilai_pengetahuan')
                    ->label('Rata-rata Pengetahuan')
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('nilai_rapor_avg_nilai_keterampilan')
                    ->label('Rata-rata Keterampilan')
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('keputusan')
                    ->badge()
                    ->placeholder('-'),
            ])
            ->headerActions([
                Action::make('tambahRapor')
                    ->label('Input Nilai Semester')
                    ->icon('heroicon-o-plus')
                    ->url(fn (): string => RaporResource::getUrl('create', ['siswa' => $this->getOwnerRecord()->getKey()]))
                    ->visible(fn (): bool => RaporResource::canCreate()),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label('Ubah Nilai')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (Rapor $record): string => RaporResource::getUrl('edit', ['record' => $record]))
                    ->visible(fn (Rapor $record): bool => RaporResource::canEdit($record)),
            ]);
    }
}
