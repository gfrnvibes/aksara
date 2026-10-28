<?php

namespace App\Filament\Resources\Rapors\Tables;

use App\Enums\Semester;
use App\Models\Rapor;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RaporsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['siswa', 'tahunAjaran', 'kelas'])
                ->scopes(['denganRataRataNilai']))
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
                TextColumn::make('rata_rata_nilai')
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
                TrashedFilter::make()
                    ->label('Data terhapus'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('cetakRapor')
                        ->label('Cetak di browser')
                        ->icon(Heroicon::OutlinedPrinter)
                        ->url(fn (Rapor $record): string => route('rapor.cetak', $record))
                        ->openUrlInNewTab(),
                    Action::make('unduhRapor')
                        ->label('Unduh PDF')
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->url(fn (Rapor $record): string => route('rapor.cetak', ['rapor' => $record, 'format' => 'pdf'])),
                ])
                    ->label('Cetak Rapor')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->link()
                    ->visible(fn (Rapor $record): bool => ! $record->trashed()),
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Rapor dipindahkan ke data terhapus dan dapat dipulihkan kembali beserta seluruh nilainya.'),
                RestoreAction::make()
                    ->label('Pulihkan'),
                ForceDeleteAction::make()
                    ->label('Hapus permanen')
                    ->modalDescription('Rapor beserta seluruh nilai mata pelajaran dan ekstrakurikulernya akan dihapus permanen dan tidak dapat dipulihkan.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalDescription('Rapor terpilih dipindahkan ke data terhapus dan dapat dipulihkan kembali beserta seluruh nilainya.'),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make()
                        ->modalDescription('Rapor terpilih beserta seluruh nilainya akan dihapus permanen dan tidak dapat dipulihkan.'),
                ]),
            ]);
    }
}
