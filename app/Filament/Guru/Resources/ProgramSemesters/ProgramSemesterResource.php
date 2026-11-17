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
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
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

    protected static string|UnitEnum|null $navigationGroup = 'Perangkat Ajar';

    protected static bool $shouldRegisterNavigation = false;

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

        return $schema
            ->columns(1)
            ->components([
            Section::make('Identitas')
                ->icon(Heroicon::OutlinedIdentification)
                ->compact()
                ->schema([
                    Grid::make(4)
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
                                    Semester::Ganjil->value => 'Semester 1 (Ganjil)',
                                    Semester::Genap->value => 'Semester 2 (Genap)',
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
                        ]),

                    Grid::make(4)
                        ->schema([
                            Select::make('fase')
                                ->label('Fase')
                                ->options([
                                    'A' => 'Fase A',
                                    'B' => 'Fase B',
                                    'C' => 'Fase C',
                                    'D' => 'Fase D',
                                    'E' => 'Fase E',
                                    'F' => 'Fase F',
                                ])
                                ->default('D')
                                ->required(),

                            TextInput::make('jp_per_minggu')
                                ->label('Alokasi JP / Minggu')
                                ->numeric()
                                ->default(4)
                                ->minValue(1)
                                ->maxValue(10)
                                ->suffix('JP')
                                ->required(),

                            TextInput::make('total_minggu_efektif')
                                ->label('Minggu Efektif')
                                ->numeric()
                                ->default(18)
                                ->minValue(1)
                                ->maxValue(25)
                                ->suffix('mgg')
                                ->required(),

                            Select::make('program_tahunan_id')
                                ->label('Tautan PROTA')
                                ->options(function () use ($gtk): array {
                                    return ProgramTahunan::query()
                                        ->where('gtk_id', $gtk->id ?? 0)
                                        ->with(['mataPelajaran', 'kelas', 'tahunAjaran'])
                                        ->get()
                                        ->mapWithKeys(fn (ProgramTahunan $p) => [$p->id => "PROTA {$p->mataPelajaran->nama} {$p->kelas->nama} ({$p->tahunAjaran->nama})"])
                                        ->all();
                                })
                                ->searchable()
                                ->nullable(),
                        ]),

                    Textarea::make('catatan')
                        ->label('Catatan / Keterangan Pelaksanaan')
                        ->rows(2)
                        ->maxLength(2000),
                ]),

            Section::make('Rincian Distribusi Pembelajaran')
                ->icon(Heroicon::OutlinedTableCells)
                ->compact()
                ->schema([
                    Repeater::make('items')
                        ->relationship('items')
                        ->orderColumn('urutan')
                        ->defaultItems(0)
                        ->reorderableWithDragAndDrop()
                        ->cloneable()
                        ->compact()
                        ->addActionLabel('Tambah Baris')
                        ->table([
                            TableColumn::make('Tujuan Pembelajaran (TP)')->markAsRequired(),
                            TableColumn::make('Materi Pokok')->markAsRequired(),
                            TableColumn::make('Alokasi JP')->width('90px')->markAsRequired(),
                            TableColumn::make('Bulan')->width('120px')->markAsRequired(),
                            TableColumn::make('Minggu Ke-')->width('120px')->markAsRequired(),
                            TableColumn::make('Keterangan'),
                        ])
                        ->schema([
                            Textarea::make('tujuan_pembelajaran')
                                ->hiddenLabel()
                                ->required()
                                ->rows(2)
                                ->maxLength(2000),

                            Textarea::make('materi_pokok')
                                ->hiddenLabel()
                                ->required()
                                ->rows(2)
                                ->maxLength(2000),

                            TextInput::make('alokasi_waktu_jp')
                                ->hiddenLabel()
                                ->numeric()
                                ->required()
                                ->default(4)
                                ->minValue(1)
                                ->maxValue(100)
                                ->suffix('JP'),

                            TextInput::make('bulan')
                                ->hiddenLabel()
                                ->required()
                                ->maxLength(100),

                            TextInput::make('minggu_ke')
                                ->hiddenLabel()
                                ->required()
                                ->maxLength(100),

                            TextInput::make('keterangan')
                                ->hiddenLabel()
                                ->maxLength(1000),
                        ]),
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
