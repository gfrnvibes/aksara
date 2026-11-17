<?php

namespace App\Filament\Guru\Resources\AlurTujuanPembelajarans;

use App\Filament\Guru\Resources\AlurTujuanPembelajarans\Pages\CreateAlurTujuanPembelajaran;
use App\Filament\Guru\Resources\AlurTujuanPembelajarans\Pages\EditAlurTujuanPembelajaran;
use App\Filament\Guru\Resources\AlurTujuanPembelajarans\Pages\ListAlurTujuanPembelajarans;
use App\Models\AlurTujuanPembelajaran;
use App\Models\Gtk;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\PembagianMengajar;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\Kurikulum\PenyusunAtpKurikulumMerdeka;
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

class AlurTujuanPembelajaranResource extends Resource
{
    protected static ?string $model = AlurTujuanPembelajaran::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Alur Tujuan Pembelajaran';

    protected static ?string $pluralModelLabel = 'Alur Tujuan Pembelajaran (ATP)';

    protected static ?string $slug = 'alur-tujuan-pembelajaran';

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

        return $gtk !== null && (! $record instanceof AlurTujuanPembelajaran || $record->gtk_id === $gtk->id)
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
                    Grid::make(5)
                        ->schema([
                            Select::make('jenjang')
                                ->label('Jenjang')
                                ->options([
                                    'SD' => 'SD / MI',
                                    'SMP' => 'SMP / MTs',
                                    'SMA' => 'SMA / MA',
                                    'SMK' => 'SMK / MAK',
                                ])
                                ->default('SMP')
                                ->required(),

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
                                        $set('jenjang', PenyusunAtpKurikulumMerdeka::tebakJenjang($tingkat));
                                        $set('fase', PenyusunAtpKurikulumMerdeka::tebakFase($tingkat));
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
                        ]),

                    Textarea::make('capaian_pembelajaran_fase')
                        ->label('Capaian Pembelajaran (CP) Fase / Elemen Umum')
                        ->rows(2)
                        ->maxLength(2000),

                    Textarea::make('catatan')
                        ->label('Catatan / Rasional / Karakteristik Peserta Didik')
                        ->rows(2)
                        ->maxLength(2000),
                ]),

            Section::make('Rincian Alur Tujuan Pembelajaran')
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
                            TableColumn::make('Elemen CP')->width('130px')->markAsRequired(),
                            TableColumn::make('Capaian Pembelajaran (CP)')->markAsRequired(),
                            TableColumn::make('Tujuan Pembelajaran (TP)')->markAsRequired(),
                            TableColumn::make('Materi Pembelajaran')->markAsRequired(),
                            TableColumn::make('Alur Tujuan Pembelajaran')->width('140px')->markAsRequired(),
                            TableColumn::make('Alokasi JP')->width('85px')->markAsRequired(),
                            TableColumn::make('Keterangan'),
                        ])
                        ->schema([
                            TextInput::make('elemen_cp')
                                ->hiddenLabel()
                                ->required()
                                ->maxLength(255),

                            Textarea::make('capaian_pembelajaran')
                                ->hiddenLabel()
                                ->required()
                                ->rows(2)
                                ->maxLength(2000),

                            Textarea::make('tujuan_pembelajaran')
                                ->hiddenLabel()
                                ->required()
                                ->rows(2)
                                ->maxLength(2000),

                            Textarea::make('materi_pembelajaran')
                                ->hiddenLabel()
                                ->required()
                                ->rows(2)
                                ->maxLength(2000),

                            TextInput::make('alur_tujuan_pembelajaran')
                                ->hiddenLabel()
                                ->required()
                                ->maxLength(255),

                            TextInput::make('alokasi_waktu_jp')
                                ->hiddenLabel()
                                ->numeric()
                                ->required()
                                ->default(4)
                                ->minValue(1)
                                ->maxValue(200)
                                ->suffix('JP'),

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

                TextColumn::make('jenjang')
                    ->label('Jenjang')
                    ->badge()
                    ->color('gray')
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
                    ->label('Jumlah TP')
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
                    ->url(fn (AlurTujuanPembelajaran $record): string => route('atp.cetak', $record))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada Alur Tujuan Pembelajaran (ATP)')
            ->emptyStateDescription('Mulai buat ATP baru untuk mata pelajaran yang Anda ampu.')
            ->emptyStateIcon(Heroicon::OutlinedQueueList);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAlurTujuanPembelajarans::route('/'),
            'create' => CreateAlurTujuanPembelajaran::route('/create'),
            'edit' => EditAlurTujuanPembelajaran::route('/{record}/edit'),
        ];
    }
}
