<?php

namespace App\Filament\Guru\Resources\ProgramTahunans;

use App\Enums\Semester;
use App\Filament\Guru\Resources\ProgramTahunans\Pages\CreateProgramTahunan;
use App\Filament\Guru\Resources\ProgramTahunans\Pages\EditProgramTahunan;
use App\Filament\Guru\Resources\ProgramTahunans\Pages\ListProgramTahunans;
use App\Models\Gtk;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\PembagianMengajar;
use App\Models\ProgramTahunan;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\Kurikulum\PenyusunProtaKurikulumMerdeka;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ProgramTahunanResource extends Resource
{
    protected static ?string $model = ProgramTahunan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Program Tahunan';

    protected static ?string $pluralModelLabel = 'Program Tahunan (PROTA)';

    protected static ?string $slug = 'program-tahunan';

    protected static string|UnitEnum|null $navigationGroup = 'Perencanaan Pembelajaran';

    /**
     * The currently logged-in teacher's GTK record.
     */
    public static function gtk(): ?Gtk
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user->gtk : null;
    }

    /**
     * Limit query to PROTAs created by or assigned to the logged-in teacher.
     */
    public static function getEloquentQuery(): Builder
    {
        $gtk = static::gtk();

        return parent::getEloquentQuery()->where('gtk_id', $gtk ? $gtk->id : 0);
    }

    /**
     * Teacher authorization logic.
     */
    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $gtk = static::gtk();

        return $gtk !== null && (! $record instanceof ProgramTahunan || $record->gtk_id === $gtk->id)
            ? Response::allow()
            : Response::deny();
    }

    public static function form(Schema $schema): Schema
    {
        $gtk = static::gtk();

        return $schema->components([
            Section::make('Identitas Program Tahunan (PROTA)')
                ->icon(Heroicon::OutlinedIdentification)
                ->description('Identitas satuan pendidikan, tahun pelajaran, kelas, fase, dan mata pelajaran.')
                ->columns(2)
                ->schema([
                    Select::make('tahun_ajaran_id')
                        ->label('Tahun Pelajaran')
                        ->options(function () use ($gtk): array {
                            $assigned = PembagianMengajar::query()->milik($gtk)->select('tahun_ajaran_id');

                            return TahunAjaran::query()
                                ->when($assigned->exists(), fn (Builder $q) => $q->whereIn('id', $assigned))
                                ->orderByDesc('is_aktif')
                                ->orderByDesc('nama')
                                ->pluck('nama', 'id')
                                ->all();
                        })
                        ->default(fn () => TahunAjaran::query()->where('is_aktif', true)->value('id'))
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('kelas_id', null)),

                    Select::make('kelas_id')
                        ->label('Kelas')
                        ->options(function (Get $get) use ($gtk): array {
                            $tahunAjaranId = $get('tahun_ajaran_id');
                            if (! $tahunAjaranId) {
                                return [];
                            }

                            $assigned = PembagianMengajar::query()->milik($gtk, (int) $tahunAjaranId)->select('kelas_id');

                            return Kelas::query()
                                ->aktif()
                                ->when($assigned->exists(), fn (Builder $q) => $q->whereIn('id', $assigned))
                                ->orderBy('tingkat')
                                ->orderBy('nama')
                                ->pluck('nama', 'id')
                                ->all();
                        })
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            $set('mata_pelajaran_id', null);
                            if ($state) {
                                $tingkat = (int) Kelas::query()->whereKey((int) $state)->value('tingkat');
                                $set('fase', PenyusunProtaKurikulumMerdeka::tebakFase($tingkat));
                            }
                        }),

                    Select::make('mata_pelajaran_id')
                        ->label('Mata Pelajaran')
                        ->options(function (Get $get) use ($gtk): array {
                            $tahunAjaranId = $get('tahun_ajaran_id');
                            $kelasId = $get('kelas_id');
                            if (! $tahunAjaranId || ! $kelasId) {
                                return [];
                            }

                            $assigned = PembagianMengajar::query()->milik($gtk, (int) $tahunAjaranId)->where('kelas_id', (int) $kelasId)->select('mata_pelajaran_id');

                            return MataPelajaran::query()
                                ->where('is_aktif', true)
                                ->when($assigned->exists(), fn (Builder $q) => $q->whereIn('id', $assigned))
                                ->orderBy('kelompok')
                                ->orderBy('urutan')
                                ->orderBy('nama')
                                ->pluck('nama', 'id')
                                ->all();
                        })
                        ->required()
                        ->searchable(),

                    Select::make('fase')
                        ->label('Fase Capaian Pembelajaran')
                        ->options([
                            'A' => 'Fase A (Kelas 1–2 SD/MI)',
                            'B' => 'Fase B (Kelas 3–4 SD/MI)',
                            'C' => 'Fase C (Kelas 5–6 SD/MI)',
                            'D' => 'Fase D (Kelas 7–9 SMP/MTs)',
                            'E' => 'Fase E (Kelas 10 SMA/MA/SMK)',
                            'F' => 'Fase F (Kelas 11–12 SMA/MA/SMK)',
                        ])
                        ->default('D')
                        ->required(),

                    Textarea::make('catatan')
                        ->label('Catatan / Keterangan Perencanaan')
                        ->rows(2)
                        ->maxLength(2000)
                        ->placeholder('Catatan khusus kalender pendidikan, asesmen, atau proyek P5...')
                        ->columnSpanFull(),
                ]),

            Section::make('Rincian Program Tahunan (PROTA)')
                ->icon(Heroicon::OutlinedTableCells)
                ->description('Rincian Capaian Pembelajaran (CP), materi pokok, tujuan pembelajaran, dan alokasi waktu semester 1 & 2.')
                ->schema([
                    Repeater::make('items')
                        ->relationship('items')
                        ->orderColumn('urutan')
                        ->defaultItems(0)
                        ->reorderableWithDragAndDrop()
                        ->collapsible()
                        ->cloneable()
                        ->addActionLabel('Tambah Baris Materi Pembelajaran')
                        ->itemLabel(function (array $state): string {
                            $sem = ($state['semester'] ?? 1) == 2 ? 'Semester 2' : 'Semester 1';
                            $elemen = $state['elemen_cp'] ?? 'Materi Baru';
                            $jp = ($state['alokasi_waktu_jp'] ?? 0).' JP';
                            $materi = ! empty($state['materi_pokok']) ? ' · '.mb_strimwidth($state['materi_pokok'], 0, 40, '...') : '';

                            return "{$sem} · [{$elemen}]{$materi} ({$jp})";
                        })
                        ->schema([
                            Select::make('semester')
                                ->label('Semester')
                                ->options([
                                    Semester::Ganjil->value => 'Semester 1 (Ganjil)',
                                    Semester::Genap->value => 'Semester 2 (Genap)',
                                ])
                                ->default(Semester::Ganjil->value)
                                ->required(),

                            TextInput::make('elemen_cp')
                                ->label('Elemen Capaian Pembelajaran (CP)')
                                ->required()
                                ->maxLength(500)
                                ->placeholder('Contoh: Bilangan / Menyimak / Pemahaman IPA'),

                            Textarea::make('materi_pokok')
                                ->label('Materi Pokok / Ruang Lingkup Materi')
                                ->required()
                                ->rows(2)
                                ->maxLength(2000)
                                ->placeholder('Topik pembelajaran yang diajarkan')
                                ->columnSpanFull(),

                            Textarea::make('tujuan_pembelajaran')
                                ->label('Tujuan Pembelajaran (TP)')
                                ->required()
                                ->rows(2)
                                ->maxLength(2000)
                                ->placeholder('Peserta didik mampu...')
                                ->columnSpanFull(),

                            TextInput::make('alokasi_waktu_jp')
                                ->label('Alokasi Waktu')
                                ->numeric()
                                ->required()
                                ->default(4)
                                ->minValue(1)
                                ->maxValue(200)
                                ->suffix('JP'),

                            TextInput::make('minggu_efektif')
                                ->label('Minggu Efektif')
                                ->numeric()
                                ->required()
                                ->default(2)
                                ->minValue(1)
                                ->maxValue(52)
                                ->suffix('minggu'),

                            TextInput::make('keterangan')
                                ->label('Keterangan')
                                ->maxLength(1000)
                                ->placeholder('Contoh: Sumatif Lingkup Materi 1 / Asesmen Akhir / P5')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tahunAjaran.nama')
                    ->label('Tahun Pelajaran')
                    ->sortable(),

                TextColumn::make('kelas.nama')
                    ->label('Kelas')
                    ->sortable(),

                TextColumn::make('fase')
                    ->label('Fase')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('mataPelajaran.nama')
                    ->label('Mata Pelajaran')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('items_count')
                    ->label('Jumlah Materi')
                    ->counts('items')
                    ->sortable(),

                TextColumn::make('items_sum_alokasi_waktu_jp')
                    ->label('Total Alokasi')
                    ->sum('items', 'alokasi_waktu_jp')
                    ->suffix(' JP')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                Action::make('cetak')
                    ->label('Cetak')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('gray')
                    ->url(fn (ProgramTahunan $record): string => route('prota.cetak', $record))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada Program Tahunan (PROTA)')
            ->emptyStateDescription('Mulai buat PROTA baru untuk mata pelajaran yang Anda ampu.')
            ->emptyStateIcon(Heroicon::OutlinedCalendarDays);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProgramTahunans::route('/'),
            'create' => CreateProgramTahunan::route('/create'),
            'edit' => EditProgramTahunan::route('/{record}/edit'),
        ];
    }
}
