<?php

namespace App\Filament\Resources\Siswas\RelationManagers;

use App\Filament\Resources\Siswas\SiswaResource;
use App\Models\Kelas;
use App\Models\RiwayatKelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The class of each academic year. Rows are written by the class promotion, by report cards and by changes to
 * the current class; here they can only be corrected by someone allowed to edit the student.
 */
class RiwayatKelasRelationManager extends RelationManager
{
    protected static string $relationship = 'riwayatKelas';

    protected static ?string $title = 'Riwayat Kelas';

    public function isReadOnly(): bool
    {
        return false;
    }

    protected function canEdit(Model $record): bool
    {
        /** @var Siswa $siswa */
        $siswa = $this->getOwnerRecord();

        return SiswaResource::canEdit($siswa);
    }

    protected function canCreate(): bool
    {
        return false;
    }

    protected function canDelete(Model $record): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('kelas_id')
                    ->label('Kelas')
                    ->relationship('kelas', 'nama', fn (Builder $query, RiwayatKelas $record): Builder => Kelas::batasiYangTersedia($query, $record->kelas_id))
                    ->preload()
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['tahunAjaran', 'kelas']))
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy(
                TahunAjaran::query()->select('nama')->whereColumn('tahun_ajarans.id', 'riwayat_kelas.tahun_ajaran_id'),
            ))
            ->columns([
                TextColumn::make('tahunAjaran.nama')
                    ->label('Tahun Ajaran'),
                TextColumn::make('kelas.nama')
                    ->label('Kelas'),
                TextColumn::make('kelas.wali_kelas')
                    ->label('Wali Kelas Saat Ini')
                    ->placeholder('-'),
            ])
            ->emptyStateHeading('Belum ada riwayat kelas')
            ->recordActions([
                EditAction::make()
                    ->label('Koreksi Kelas')
                    ->modalHeading(fn (RiwayatKelas $record): string => "Koreksi kelas tahun ajaran {$record->tahunAjaran->nama}"),
            ]);
    }
}
