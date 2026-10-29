<?php

namespace App\Filament\Resources\Ijazahs;

use App\Filament\Resources\Ijazahs\Pages\ManageIjazahs;
use App\Filament\Resources\Ijazahs\Pages\UnggahIjazahMassal;
use App\Filament\Resources\Siswas\SiswaResource;
use App\Models\Ijazah;
use App\Models\Siswa;
use App\Services\Arsip\SimpanBerkasArsip;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

class IjazahResource extends Resource
{
    protected static ?string $model = Ijazah::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'Arsip';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Arsip Ijazah';

    protected static ?string $modelLabel = 'Ijazah';

    protected static ?string $pluralModelLabel = 'Arsip Ijazah';

    protected static ?string $slug = 'arsip-ijazah';

    /**
     * File types accepted for a diploma scan.
     *
     * @var list<string>
     */
    public const array JENIS_BERKAS = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    /**
     * Largest upload per file, in kilobytes, before images are compressed.
     */
    public const int UKURAN_MAKS_KB = 20480;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('tahun_lulus')
                    ->label('Tahun Lulus')
                    ->numeric()
                    ->integer()
                    ->minValue(1950)
                    ->maxValue((int) now()->year + 1)
                    ->default((int) now()->year)
                    ->live(onBlur: true)
                    ->required(),
                Select::make('siswa_id')
                    ->label('Siswa di Buku Induk')
                    ->relationship('siswa', 'nama_lengkap', fn (Builder $query): Builder => $query->orderBy('nama_lengkap'))
                    ->getOptionLabelFromRecordUsing(fn (Siswa $record): string => "{$record->nis} - {$record->nama_lengkap}")
                    ->searchable(['nama_lengkap', 'nis', 'nisn'])
                    ->preload()
                    ->optionsLimit(SiswaResource::BATAS_PILIHAN)
                    ->helperText('Opsional. Alumni yang tidak ada di Data Siswa cukup diisi nama dan nomornya.')
                    ->live()
                    ->afterStateUpdated(function (?int $state, Set $set): void {
                        if ($siswa = $state ? Siswa::withTrashed()->find($state) : null) {
                            $set('nama', $siswa->nama_lengkap);
                            $set('nis', $siswa->nis);
                            $set('nisn', $siswa->nisn);
                        }
                    }),
                TextInput::make('nama')
                    ->label('Nama Lulusan')
                    ->maxLength(255)
                    ->required(),
                TextInput::make('nomor_seri')
                    ->label('Nomor Seri Ijazah')
                    ->maxLength(100)
                    ->unique(ignoreRecord: true),
                TextInput::make('nis')
                    ->label('NIS')
                    ->maxLength(30),
                TextInput::make('nisn')
                    ->label('NISN')
                    ->maxLength(20),
                DatePicker::make('tanggal_ijazah')
                    ->label('Tanggal Ijazah'),
                Textarea::make('keterangan')
                    ->rows(2),
                FileUpload::make('berkas')
                    ->label('Berkas Ijazah')
                    ->helperText('PDF atau gambar (JPG, PNG, WebP), bisa lebih dari satu halaman. Gambar otomatis diperkecil agar hemat ruang.')
                    ->multiple()
                    ->reorderable()
                    ->appendFiles()
                    ->maxFiles(10)
                    ->disk(fn (): string => Ijazah::disk())
                    ->visibility('private')
                    ->acceptedFileTypes(static::JENIS_BERKAS)
                    ->maxSize(static::UKURAN_MAKS_KB)
                    ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file, Get $get): string => app(SimpanBerkasArsip::class)
                        ->simpan($file, 'ijazah/'.($get('tahun_lulus') ?: 'tanpa-tahun')))
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('siswa'))
            ->defaultGroup(Group::make('tahun_lulus')->label('Tahun Lulus')->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderBy('tahun_lulus', 'desc')))
            ->defaultSort('nama')
            ->columns([
                TextColumn::make('nama')
                    ->label('Nama Lulusan')
                    ->placeholder('Nama belum diisi')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nis')
                    ->label('NIS')
                    ->searchable(),
                TextColumn::make('nisn')
                    ->label('NISN')
                    ->searchable(),
                TextColumn::make('nomor_seri')
                    ->label('No. Seri')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('berkas')
                    ->label('Berkas')
                    ->state(fn (Ijazah $record): int => count($record->berkas ?? []))
                    ->formatStateUsing(fn (int $state): string => $state === 0 ? 'Belum ada' : "{$state} file")
                    ->badge()
                    ->color(fn (int $state): string => $state === 0 ? 'danger' : 'success'),
                IconColumn::make('siswa_id')
                    ->label('Di Buku Induk')
                    ->state(fn (Ijazah $record): bool => $record->siswa_id !== null)
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('tahun_lulus')
                    ->label('Tahun Lulus')
                    ->options(fn (): array => Ijazah::query()->distinct()->orderByDesc('tahun_lulus')->pluck('tahun_lulus', 'tahun_lulus')->all()),
                Filter::make('belum_ada_berkas')
                    ->label('Belum ada berkas')
                    ->query(fn (Builder $query): Builder => $query->where(fn (Builder $query) => $query->whereNull('berkas')->orWhereJsonLength('berkas', 0))),
                Filter::make('data_belum_lengkap')
                    ->label('Nama atau nomor belum diisi')
                    ->query(fn (Builder $query): Builder => $query->where(fn (Builder $query) => $query->whereNull('nama')->orWhere(fn (Builder $query) => $query->whereNull('nis')->whereNull('nisn')))),
            ])
            ->recordActions([
                static::aksiLihat(),
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Data ijazah beserta seluruh berkasnya akan dihapus permanen.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalDescription('Data ijazah terpilih beserta seluruh berkasnya akan dihapus permanen.'),
                ]),
            ]);
    }

    /**
     * Shows every page of the diploma — PDFs in the browser's viewer, images inline — through short-lived links,
     * and records who looked at it.
     */
    public static function aksiLihat(): Action
    {
        return Action::make('lihat')
            ->label('Lihat')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->visible(fn (Ijazah $record): bool => filled($record->berkas))
            ->authorize(fn (Ijazah $record): bool => static::canView($record))
            ->modalHeading(fn (Ijazah $record): string => 'Ijazah '.($record->nama ?? $record->nis ?? '')." ({$record->tahun_lulus})")
            ->modalWidth('7xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->mountUsing(fn (Ijazah $record) => activity()
                ->performedOn($record)
                ->event('ijazah_dilihat')
                ->log('Berkas ijazah dilihat'))
            ->modalContent(function (Ijazah $record) {
                return view('filament.arsip.pratinjau-ijazah', [
                    'berkas' => array_map(fn (string $path): array => [
                        'url' => $record->tautanSementara($path),
                        'pdf' => Ijazah::adalahPdf($path),
                        'nama' => basename($path),
                    ], $record->berkas ?? []),
                ]);
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageIjazahs::route('/'),
            'unggah' => UnggahIjazahMassal::route('/unggah'),
        ];
    }
}
