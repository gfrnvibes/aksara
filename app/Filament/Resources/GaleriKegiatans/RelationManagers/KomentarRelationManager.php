<?php

namespace App\Filament\Resources\GaleriKegiatans\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class KomentarRelationManager extends RelationManager
{
    protected static string $relationship = 'komentar';

    protected static ?string $title = 'Komentar Pengunjung';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('nama_pengomentar')
                    ->label('Nama')
                    ->searchable()
                    ->description(fn ($record): ?string => $record->peran),

                TextColumn::make('isi')
                    ->label('Komentar')
                    ->wrap()
                    ->searchable(),

                ToggleColumn::make('is_tampil')
                    ->label('Tampilkan'),

                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada komentar')
            ->emptyStateDescription('Komentar dari pengunjung landing page akan muncul di sini untuk dimoderasi.');
    }
}
