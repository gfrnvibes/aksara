<?php

namespace App\Filament\Guru\Resources\ProgramSemesters;

use App\Enums\Semester;
use App\Filament\Guru\Resources\ProgramSemesters\Pages\CreateProgramSemester;
use App\Filament\Guru\Resources\ProgramSemesters\Pages\EditProgramSemester;
use App\Filament\Guru\Resources\ProgramSemesters\Pages\ListProgramSemesters;
use App\Models\Gtk;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\PembagianMengajar;
use App\Models\ProgramSemester;
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

class ProgramSemesterResource extends Resource
{
    protected static ?string $model = ProgramSemester::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Program Semester';

    protected static ?string $pluralModelLabel = 'Program Semester (PROSEM)';

    protected static ?string $slug = 'program-semester';

    protected static string|UnitEnum|null $navigationGroup = 'Perencanaan Pembelajaran';

    public static function gtk(): ?Gtk
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user->gtk : null;
    }

    public static function getEloquentQuery(): Builder
    {
        $gtk = static::gtk();

        return parent::getEloquentQuery()->where('gtk_id', $gtk ? $gtk->id : 0);
    }

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $gtk = static::gtk();

        return $gtk !== null && (! $record instanceof ProgramSemester || $record->gtk_id === $gtk->id)
            ? Response::allow()
            : Response::deny();
    }

    public static function form(Schema $schema): Schema
    {
        $gtk = static::gtk();

        return $schema->components([
            Section::make('Identitas Program Semester (PROSEM)')
                ->icon(Heroicon::OutlinedIdentification)
                ->description('Identitas sekolah/madrasah, tahun pelajaran, semester, kelas, fase, dan alokasi JP per minggu.')
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
                        ->live(),

                    Select::make('semester')
                        ->label('Semester')
                        ->options([
                            Semester::Ganjil->value => 'Semester 1 (Ganjil - Juli s.d. Desember)',
                            Semester::Genap->value => 'Semester 2 (Genap - Januari s.d. Juni)',
                        ])
                        ->default(Semester::Ganjil->value)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?int $state): void {
                            $set('total_minggu_efektif', $state === Semester::Genap->value ? 16 : 18);
                        }),

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
                        ->afterStateUpdated(function (Set $set, ?string $state, Get $get): void {
                            $set('mata_pelajaran_id', null);
                            if ($state) {
                                $tingkat = (int) Kelas::query()->whereKey((int) $state)->value('tingkat');
                                $set('fase', PenyusunProtaKurikulumMerdeka::tebakFase($tingkat));

                                $tahunId = $get('tahun_ajaran_id');
                                $mapelId = $get('mata_pelajaran_id');
                                if ($tahunId && $mapelId) {
                                    $jp = PembagianMengajar::query()
                                        ->where('tahun_ajaran_id', (int) $tahunId)
                                        ->where('kelas_id', (int) $state)
                                        ->where('mata_pelajaran_id', (int) $mapelId)
                                        ->value('jp_per_minggu');
                                    if ($jp) {
                                        $set('jp_per_minggu', $jp);
                                    }
                                }
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
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?string $state, Get $get) use ($gtk): void {
                            $tahunId = $get('tahun_ajaran_id');
                            $kelasId = $get('kelas_id');
                            if ($tahunId && $kelasId && $state) {
                                $jp = PembagianMengajar::query()
                                    ->where('tahun_ajaran_id', (int) $tahunId)
                                    ->where('kelas_id', (int) $kelasId)
                                    ->where('mata_pelajaran_id', (int) $state)
                                    ->value('jp_per_minggu');
                                if ($jp) {
                                    $set('jp_per_minggu', $jp);
                                }

                                $protaId = ProgramTahunan::query()
                                    ->where('tahun_ajaran_id', (int) $tahunId)
                                    ->where('kelas_id', (int) $kelasId)
                                    ->where('mata_pelajaran_id', (int) $state)
                                    ->where('gtk_id', $gtk->id)
                                    ->value('id');
                                if ($protaId) {
                                    $set('program_tahunan_id', $protaId);
                                }
                            }
                        }),

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

                    TextInput::make('jp_per_minggu')
                        ->label('Alokasi JP per Minggu')
                        ->numeric()
                        ->default(4)
                        ->minValue(1)
                        ->maxValue(10)
                        ->suffix('JP / minggu')
                        ->required(),

                    TextInput::make('total_minggu_efektif')
                        ->label('Jumlah Minggu Efektif')
                        ->numeric()
                        ->default(18)
                        ->minValue(1)
                        ->maxValue(25)
                        ->suffix('minggu')
                        ->required(),

                    Select::make('program_tahunan_id')
                        ->label('Tautan Program Tahunan (PROTA)')
                        ->options(function () use ($gtk): array {
                            return ProgramTahunan::query()
                                ->where('gtk_id', $gtk->id ?? 0)
                                ->with(['mataPelajaran', 'kelas', 'tahunAjaran'])
                                ->get()
                                ->mapWithKeys(fn (ProgramTahunan $p) => [$p->id => "PROTA {$p->mataPelajaran->nama} {$p->kelas->nama} ({$p->tahunAjaran->nama})"])
                                ->all();
                        })
                        ->searchable()
                        ->nullable()
                        ->helperText('Hubungkan dengan PROTA terkait agar sinkron.'),

                    Textarea::make('catatan')
                        ->label('Catatan / Keterangan Pelaksanaan')
                        ->rows(2)
                        ->maxLength(2000)
                        ->placeholder('Catatan kalender pendidikan, agenda libur, jadwal asesmen...')
                        ->columnSpanFull(),
                ]),

            Section::make('Rincian Distribusi Pembelajaran Semester')
                ->icon(Heroicon::OutlinedTableCells)
                ->description('Rincian Tujuan Pembelajaran, materi pokok, alokasi waktu JP, serta pemetaan bulan dan minggu ke- berapa.')
                ->schema([
                    Repeater::make('items')
                        ->relationship('items')
                        ->orderColumn('urutan')
                        ->defaultItems(0)
                        ->reorderableWithDragAndDrop()
                        ->collapsible()
                        ->cloneable()
                        ->addActionLabel('Tambah Materi Pembelajaran')
                        ->itemLabel(function (array $state): string {
                            $bulan = $state['bulan'] ?? 'Bulan';
                            $minggu = $state['minggu_ke'] ?? 'Minggu';
                            $materi = ! empty($state['materi_pokok']) ? mb_strimwidth($state['materi_pokok'], 0, 40, '...') : 'Materi';
                            $jp = ($state['alokasi_waktu_jp'] ?? 0).' JP';

                            return "{$bulan} ({$minggu}) · {$materi} ({$jp})";
                        })
                        ->schema([
                            Textarea::make('tujuan_pembelajaran')
                                ->label('Tujuan Pembelajaran (TP)')
                                ->required()
                                ->rows(2)
                                ->maxLength(2000)
                                ->placeholder('Peserta didik mampu...')
                                ->columnSpanFull(),

                            Textarea::make('materi_pokok')
                                ->label('Materi Pokok / Lingkup Materi')
                                ->required()
                                ->rows(2)
                                ->maxLength(2000)
                                ->placeholder('Topik pembelajaran yang diajarkan')
                                ->columnSpanFull(),

                            TextInput::make('alokasi_waktu_jp')
                                ->label('Alokasi Waktu')
                                ->numeric()
                                ->required()
                                ->default(4)
                                ->minValue(1)
                                ->maxValue(100)
                                ->suffix('JP'),

                            TextInput::make('bulan')
                                ->label('Bulan Pelaksanaan')
                                ->required()
                                ->maxLength(100)
                                ->placeholder('Contoh: Juli / Agustus'),

                            TextInput::make('minggu_ke')
                                ->label('Minggu ke-')
                                ->required()
                                ->maxLength(100)
                                ->placeholder('Contoh: Minggu ke-3, 4'),

                            TextInput::make('keterangan')
                                ->label('Keterangan Asesmen / Kegiatan')
                                ->maxLength(1000)
                                ->placeholder('Contoh: Formatif / Sumatif LM 1 / STS / P5 / Cadangan')
                                ->columnSpanFull(),
                        ])
                        ->columns(3),
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

                TextColumn::make('semester')
                    ->label('Semester')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => $state === 2 ? 'Semester 2 (Genap)' : 'Semester 1 (Ganjil)')
                    ->color(fn (int $state): string => $state === 2 ? 'info' : 'warning')
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

                TextColumn::make('jp_per_minggu')
                    ->label('JP/Mgg')
                    ->suffix(' JP')
                    ->sortable(),

                TextColumn::make('items_count')
                    ->label('Jumlah TP')
                    ->counts('items')
                    ->sortable(),

                TextColumn::make('items_sum_alokasi_waktu_jp')
                    ->label('Total JP')
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
                    ->url(fn (ProgramSemester $record): string => route('prosem.cetak', $record))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada Program Semester (PROSEM)')
            ->emptyStateDescription('Mulai buat PROSEM baru atau generate langsung dengan AI Gemini.')
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProgramSemesters::route('/'),
            'create' => CreateProgramSemester::route('/create'),
            'edit' => EditProgramSemester::route('/{record}/edit'),
        ];
    }
}
