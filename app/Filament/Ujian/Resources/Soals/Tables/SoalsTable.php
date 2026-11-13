<?php

namespace App\Filament\Ujian\Resources\Soals\Tables;

use App\Enums\JenisSoal;
use App\Filament\Actions\HapusMasterDataActions;
use App\Filament\Resources\Kelas\KelasResource;
use App\Filament\Ujian\Resources\Soals\Pages\ListSoals;
use App\Filament\Ujian\Resources\Ujians\UjianResource;
use App\Models\Soal;
use App\Models\Ujian;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class SoalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('pembuat'))
            ->columns([
                TextColumn::make('pertanyaan')
                    ->formatStateUsing(fn (string $state): string => str(html_entity_decode(strip_tags($state)))->squish()->limit(120)->toString())
                    ->description(fn (Soal $record): string => $record->materi)
                    ->searchable(['pertanyaan', 'materi'])
                    ->wrap(),
                TextColumn::make('tingkat')
                    ->label('Kelas')
                    ->formatStateUsing(fn (int $state): string => KelasResource::getTingkatOptions()[$state] ?? (string) $state)
                    ->visible(fn (ListSoals $livewire): bool => $livewire->tingkatAktif() === null),
                TextColumn::make('jenis')
                    ->badge(),
                TextColumn::make('level_kognitif')
                    ->label('Level')
                    ->formatStateUsing(fn (Soal $record): string => $record->level_kognitif ? "{$record->level_kognitif->value} · {$record->level_kognitif->level()}" : '-'),
                IconColumn::make('is_siap')
                    ->label('Siap')
                    ->boolean(),
                TextColumn::make('pembuat.name')
                    ->label('Pembuat')
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('jenis')
                    ->options(JenisSoal::class),
                TernaryFilter::make('is_siap')
                    ->label('Status')
                    ->trueLabel('Siap')
                    ->falseLabel('Draf'),
            ])
            ->recordActions([
                ViewAction::make()->hidden(fn (Soal $record, ListRecords $livewire): bool => $livewire::getResource()::canEdit($record)),
                EditAction::make(),
                HapusMasterDataActions::hapus(),
            ])
            ->toolbarActions([
                static::tandaiSiap(),
                static::masukkanKeUjian(),
            ]);
    }

    /**
     * Mark the ticked drafts checked, only those the user may change (their own, or all for curriculum staff).
     */
    protected static function tandaiSiap(): BulkAction
    {
        return BulkAction::make('tandaiSiap')
            ->label('Tandai Siap Dipakai')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->requiresConfirmation()
            ->modalDescription('Pastikan setiap soal dan kuncinya sudah diperiksa. Soal milik guru lain dilewati.')
            ->action(function (Collection $records, ListRecords $livewire): void {
                $boleh = $records->filter(fn (Soal $soal): bool => ! $soal->is_siap && $livewire::getResource()::canEdit($soal));

                // One save per question so each lands in the activity log.
                $boleh->each(fn (Soal $soal) => $soal->update(['is_siap' => true]));

                $dilewati = $records->count() - $boleh->count();

                Notification::make()->success()->title("{$boleh->count()} soal ditandai siap")
                    ->body($dilewati > 0 ? "{$dilewati} soal dilewati karena sudah siap atau bukan milik Anda." : null)
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * Add the ticked questions to an exam the user may change, after any questions it already has.
     */
    protected static function masukkanKeUjian(): BulkAction
    {
        return BulkAction::make('masukkanKeUjian')
            ->label('Masukkan ke Ujian')
            ->icon(Heroicon::OutlinedDocumentPlus)
            ->modalDescription('Hanya soal berstatus siap yang dimasukkan. Ujian yang tampil adalah ujian mapel dan kelas ini yang bisa Anda ubah.')
            ->schema([
                Select::make('ujian_id')
                    ->label('Ujian')
                    ->options(fn (ListSoals $livewire): array => Ujian::query()
                        ->whereIn('id', UjianResource::getEloquentQuery()->select('id'))
                        ->where('mata_pelajaran_id', $livewire->mapel)
                        ->when($livewire->tingkatAktif(), fn ($query, int $tingkat) => $query->where('tingkat', $tingkat))
                        ->with('tahunAjaran')
                        ->latest('id')
                        ->get()
                        ->filter(fn (Ujian $ujian): bool => UjianResource::canEdit($ujian))
                        ->mapWithKeys(fn (Ujian $ujian): array => [$ujian->id => "{$ujian->nama} · Kelas ".(KelasResource::getTingkatOptions()[$ujian->tingkat] ?? $ujian->tingkat)." ({$ujian->tahunAjaran->nama})"])
                        ->all())
                    ->searchable()
                    ->required(),
            ])
            ->action(function (Collection $records, array $data): void {
                $ujian = Ujian::query()->whereKey($data['ujian_id'])->firstOrFail();
                abort_unless(UjianResource::canEdit($ujian), 403);

                $sudahAda = $ujian->butir()->pluck('soal_id');
                $baru = $records->filter(fn (Soal $soal): bool => $soal->is_siap
                    && $soal->mata_pelajaran_id === $ujian->mata_pelajaran_id
                    && $soal->tingkat === $ujian->tingkat
                    && ! $sudahAda->contains($soal->id));
                $urutan = (int) $ujian->butir()->max('urutan');

                foreach ($baru as $soal) {
                    $ujian->butir()->create(['soal_id' => $soal->id, 'urutan' => ++$urutan, 'bobot' => 1]);
                }

                $dilewati = $records->count() - $baru->count();

                Notification::make()->success()->title("{$baru->count()} soal dimasukkan ke {$ujian->nama}")
                    ->body($dilewati > 0 ? "{$dilewati} soal dilewati karena sudah ada di ujian, masih draf, atau mapel/kelasnya berbeda." : null)
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
