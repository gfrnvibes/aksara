<?php

namespace App\Filament\Resources\SuratKeluars\Tables;

use App\Enums\JenisSuratKeluar;
use App\Filament\Resources\SuratKeluars\SuratKeluarResource;
use App\Models\SuratKeluar;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SuratKeluarsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(fn ($query) => $query->orderByDesc('tahun')->orderByDesc('nomor_urut'))
            ->columns([
                TextColumn::make('nomor')
                    ->searchable()
                    ->fontFamily('mono'),
                TextColumn::make('tanggal')
                    ->date('d-m-Y')
                    ->sortable(),
                TextColumn::make('jenis')
                    ->badge(),
                TextColumn::make('perihal')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('tujuan')
                    ->searchable()
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('nama_kepala_madrasah')
                    ->label('Penanda Tangan')
                    ->state(fn (SuratKeluar $record): ?string => $record->kepalaMadrasah()['nama'])
                    ->placeholder('Kepala madrasah belum diisi')
                    ->description(fn (SuratKeluar $record): ?string => filled($record->nama_penanda_tangan) ? "+ {$record->nama_penanda_tangan} ({$record->jabatan_penanda_tangan})" : null)
                    ->toggleable(),
                TextColumn::make('sifat')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('jenis')
                    ->options(JenisSuratKeluar::class),
                SelectFilter::make('tahun')
                    ->options(fn (): array => SuratKeluar::query()->distinct()->orderByDesc('tahun')->pluck('tahun', 'tahun')->all()),
            ])
            ->recordActions([
                static::aksiCetak(),
                static::aksiDuplikat(),
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Nomor surat yang dihapus tidak dipakai ulang.'),
            ]);
    }

    public static function aksiCetak(): ActionGroup
    {
        return ActionGroup::make([
            Action::make('cetakSurat')
                ->label('Cetak di browser')
                ->icon(Heroicon::OutlinedPrinter)
                ->url(fn (SuratKeluar $record): string => route('surat.keluar', $record))
                ->openUrlInNewTab(),
            Action::make('unduhSurat')
                ->label('Unduh PDF')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->url(fn (SuratKeluar $record): string => route('surat.keluar', ['surat' => $record, 'format' => 'pdf'])),
        ])
            ->label('Cetak')
            ->icon(Heroicon::OutlinedPrinter)
            ->link();
    }

    /**
     * Start a new letter from this one: same contents, today's date and the next number.
     */
    public static function aksiDuplikat(): Action
    {
        return Action::make('duplikat')
            ->label('Duplikat')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('Surat baru dibuat dengan isi yang sama, tanggal hari ini, dan nomor berikutnya.')
            ->authorize(fn (): bool => SuratKeluarResource::canCreate())
            ->action(function (SuratKeluar $record, Action $action): void {
                $baru = $record->replicate(['tahun', 'nomor_urut', 'nomor', 'dibuat_oleh', 'created_at', 'updated_at']);
                $baru->tanggal = now();
                $baru->save();

                $action->redirect(SuratKeluarResource::getUrl('edit', ['record' => $baru]));
            });
    }
}
