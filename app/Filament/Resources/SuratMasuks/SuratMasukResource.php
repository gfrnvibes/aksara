<?php

namespace App\Filament\Resources\SuratMasuks;

use App\Enums\SifatSurat;
use App\Enums\StatusSuratMasuk;
use App\Filament\Resources\Ijazahs\IjazahResource;
use App\Filament\Resources\PenandaTangans\PenandaTanganResource;
use App\Filament\Resources\SuratMasuks\Pages\ManageSuratMasuks;
use App\Models\SuratMasuk;
use App\Services\Arsip\SimpanBerkasArsip;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

class SuratMasukResource extends Resource
{
    protected static ?string $model = SuratMasuk::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Persuratan';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Surat Masuk';

    protected static ?string $pluralModelLabel = 'Surat Masuk';

    protected static ?string $slug = 'surat-masuk';

    /**
     * Instructions commonly written on a disposition slip.
     *
     * @var list<string>
     */
    public const array INSTRUKSI_UMUM = [
        'Untuk diketahui',
        'Untuk ditindaklanjuti',
        'Mohon dihadiri',
        'Mohon diwakili',
        'Siapkan jawaban',
        'Untuk diedarkan',
        'Arsipkan',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                DatePicker::make('tanggal_diterima')
                    ->label('Tanggal Diterima')
                    ->default(now())
                    ->required(),
                Select::make('sifat')
                    ->options(SifatSurat::class)
                    ->default(SifatSurat::Biasa)
                    ->required(),
                TextInput::make('pengirim')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('nomor_surat')
                    ->label('Nomor Surat')
                    ->maxLength(255),
                DatePicker::make('tanggal_surat')
                    ->label('Tanggal Surat'),
                TextInput::make('perihal')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('ringkasan')
                    ->label('Ringkasan Isi')
                    ->rows(3)
                    ->columnSpanFull(),
                FileUpload::make('berkas')
                    ->label('Scan Surat')
                    ->helperText('PDF atau gambar, bisa lebih dari satu halaman. Gambar otomatis diperkecil.')
                    ->multiple()
                    ->reorderable()
                    ->appendFiles()
                    ->maxFiles(10)
                    ->disk(fn (): string => SuratMasuk::disk())
                    ->visibility('private')
                    ->acceptedFileTypes(IjazahResource::JENIS_BERKAS)
                    ->maxSize(IjazahResource::UKURAN_MAKS_KB)
                    ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file, Get $get): string => app(SimpanBerkasArsip::class)
                        ->simpan($file, 'surat-masuk/'.(filled($get('tanggal_diterima')) ? Carbon::parse($get('tanggal_diterima'))->year : now()->year)))
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(fn ($query) => $query->orderByDesc('tahun')->orderByDesc('nomor_agenda'))
            ->columns([
                TextColumn::make('nomor_agenda')
                    ->label('No. Agenda')
                    ->formatStateUsing(fn (SuratMasuk $record): string => $record->labelAgenda())
                    ->fontFamily('mono'),
                TextColumn::make('tanggal_diterima')
                    ->label('Diterima')
                    ->date('d-m-Y')
                    ->sortable(),
                TextColumn::make('pengirim')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('perihal')
                    ->searchable()
                    ->wrap()
                    ->description(fn (SuratMasuk $record): ?string => $record->nomor_surat),
                TextColumn::make('sifat')
                    ->badge(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('disposisi_kepada')
                    ->label('Disposisi Kepada')
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(StatusSuratMasuk::class),
                SelectFilter::make('sifat')
                    ->options(SifatSurat::class),
                SelectFilter::make('tahun')
                    ->options(fn (): array => SuratMasuk::query()->distinct()->orderByDesc('tahun')->pluck('tahun', 'tahun')->all()),
            ])
            ->recordActions([
                static::aksiLihat(),
                static::aksiDisposisi(),
                Action::make('selesai')
                    ->label('Selesai')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Tandai surat selesai ditindaklanjuti?')
                    ->visible(fn (SuratMasuk $record): bool => $record->status !== StatusSuratMasuk::Selesai)
                    ->authorize(fn (SuratMasuk $record): bool => static::canEdit($record))
                    ->action(fn (SuratMasuk $record) => $record->update(['status' => StatusSuratMasuk::Selesai])),
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Data surat masuk beserta scan-nya akan dihapus permanen.'),
            ]);
    }

    /**
     * Pass the letter on to an official, with what they should do about it.
     */
    public static function aksiDisposisi(): Action
    {
        return Action::make('disposisi')
            ->label('Disposisi')
            ->icon(Heroicon::OutlinedArrowUturnRight)
            ->color('info')
            ->modalHeading(fn (SuratMasuk $record): string => "Disposisi surat agenda {$record->labelAgenda()}")
            ->modalWidth('lg')
            ->authorize(fn (SuratMasuk $record): bool => static::canEdit($record))
            ->fillForm(fn (SuratMasuk $record): array => [
                'disposisi_kepada' => $record->disposisi_kepada,
                'disposisi_instruksi' => $record->disposisi_instruksi,
                'disposisi_tanggal' => $record->disposisi_tanggal ?? now(),
            ])
            ->schema([
                TextInput::make('disposisi_kepada')
                    ->label('Diteruskan Kepada')
                    ->datalist(['Kepala Madrasah', ...PenandaTanganResource::JABATAN_UMUM, 'Wali Kelas', 'Guru Mata Pelajaran', 'Operator'])
                    ->required()
                    ->maxLength(255),
                Textarea::make('disposisi_instruksi')
                    ->label('Instruksi / Catatan')
                    ->helperText('Misalnya: '.implode(', ', array_slice(static::INSTRUKSI_UMUM, 0, 4)).'.')
                    ->rows(3)
                    ->required(),
                DatePicker::make('disposisi_tanggal')
                    ->label('Tanggal Disposisi')
                    ->required(),
            ])
            ->action(fn (array $data, SuratMasuk $record) => $record->update([
                ...$data,
                'status' => $record->status === StatusSuratMasuk::Selesai ? StatusSuratMasuk::Selesai : StatusSuratMasuk::Didisposisikan,
            ]));
    }

    public static function aksiLihat(): Action
    {
        return Action::make('lihat')
            ->label('Lihat')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->visible(fn (SuratMasuk $record): bool => filled($record->berkas))
            ->authorize(fn (SuratMasuk $record): bool => static::canView($record))
            ->modalHeading(fn (SuratMasuk $record): string => "Surat masuk agenda {$record->labelAgenda()}")
            ->modalWidth('7xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->modalContent(fn (SuratMasuk $record) => view('filament.arsip.pratinjau-berkas', ['berkas' => $record->berkasUntukPratinjau()]));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSuratMasuks::route('/'),
        ];
    }
}
