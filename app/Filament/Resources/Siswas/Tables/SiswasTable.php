<?php

namespace App\Filament\Resources\Siswas\Tables;

use App\Enums\JenisKelamin;
use App\Enums\StatusSiswa;
use App\Filament\Actions\EksporBukuIndukActions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SiswasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('nis')
            ->columns([
                ImageColumn::make('foto')
                    ->disk('public')
                    ->circular(),
                TextColumn::make('nis')
                    ->label('NIS')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nisn')
                    ->label('NISN')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('nama_lengkap')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('jenis_kelamin')
                    ->label('L/P')
                    ->formatStateUsing(fn (JenisKelamin $state): string => $state->value),
                TextColumn::make('kelas.nama')
                    ->label('Kelas')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('rapor_count')
                    ->label('Rapor')
                    ->counts('rapor')
                    ->suffix(' semester')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('kelas')
                    ->relationship('kelas', 'nama')
                    ->preload(),
                SelectFilter::make('status')
                    ->options(StatusSiswa::class),
                SelectFilter::make('jenis_kelamin')
                    ->options(JenisKelamin::class),
            ])
            ->recordActions([
                EditAction::make(),
                EksporBukuIndukActions::cetakSatuSiswa(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    EksporBukuIndukActions::exportSiswaTerpilih(),
                    EksporBukuIndukActions::cetakSiswaTerpilih(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
