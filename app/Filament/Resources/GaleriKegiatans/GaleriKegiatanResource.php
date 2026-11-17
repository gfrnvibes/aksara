<?php

namespace App\Filament\Resources\GaleriKegiatans;

use App\Filament\Resources\GaleriKegiatans\Pages\CreateGaleriKegiatan;
use App\Filament\Resources\GaleriKegiatans\Pages\EditGaleriKegiatan;
use App\Filament\Resources\GaleriKegiatans\Pages\ListGaleriKegiatans;
use App\Filament\Resources\GaleriKegiatans\RelationManagers\KomentarRelationManager;
use App\Models\GaleriKegiatan;
use App\Services\Galeri\PenyusunDeskripsiGaleriGemini;
use App\Services\Gemini\KlienGemini;
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
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Throwable;
use UnitEnum;

class GaleriKegiatanResource extends Resource
{
    protected static ?string $model = GaleriKegiatan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Publikasi';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Galeri Kegiatan';

    protected static ?string $pluralModelLabel = 'Galeri Kegiatan Siswa';

    protected static ?string $slug = 'galeri-kegiatan';

    protected static ?string $recordTitleAttribute = 'judul';

    /**
     * @var list<string>
     */
    public const array JENIS_MEDIA = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'video/mp4',
        'video/webm',
        'video/quicktime',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi Kegiatan')
                ->compact()
                ->columns(3)
                ->schema([
                    TextInput::make('judul')
                        ->label('Judul Kegiatan')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Contoh: Peringatan Maulid Nabi & Pentas Seni Santri')
                        ->columnSpanFull(),

                    Select::make('kategori')
                        ->label('Kategori')
                        ->options(GaleriKegiatan::KATEGORI)
                        ->default('Kesiswaan')
                        ->required()
                        ->searchable(),

                    DatePicker::make('tanggal_kegiatan')
                        ->label('Tanggal Kegiatan')
                        ->default(now())
                        ->required(),

                    Toggle::make('is_publik')
                        ->label('Tampilkan di Landing Page')
                        ->inline(false)
                        ->hintIcon(Heroicon::OutlinedInformationCircle, tooltip: 'Jika aktif, foto/video kegiatan ini akan muncul di galeri Pinterest pada halaman depan.')
                        ->default(true),

                    Textarea::make('deskripsi')
                        ->label('Deskripsi / Cerita Singkat')
                        ->rows(2)
                        ->maxLength(2000)
                        ->placeholder('Ceritakan momen penting dalam kegiatan ini untuk ditampilkan di galeri publik...')
                        ->hintAction(
                            Action::make('susunDeskripsiAi')
                                ->label('Rapikan / Buat dengan AI')
                                ->icon(Heroicon::OutlinedSparkles)
                                ->color('primary')
                                ->modalHeading('Susun Deskripsi Kegiatan dengan AI Gemini')
                                ->modalDescription('AI akan merapikan draf atau membuatkan deskripsi baru berdasarkan Judul Kegiatan dan arahan gaya bahasa yang Anda pilih.')
                                ->modalSubmitActionLabel('Generate Deskripsi')
                                ->modalWidth('lg')
                                ->fillForm(fn (Get $get): array => [
                                    'gaya' => 'ramah_emoji',
                                    'catatan_awal' => (string) ($get('deskripsi') ?? ''),
                                ])
                                ->schema([
                                    Select::make('gaya')
                                        ->label('Gaya Bahasa')
                                        ->options([
                                            'ramah_emoji' => 'Ramah, Hangat & Ber-emoji ✨',
                                            'formal' => 'Formal & Rapi (Resmi Madrasah)',
                                            'singkat' => 'Singkat & Padat',
                                        ])
                                        ->default('ramah_emoji')
                                        ->required(),

                                    Textarea::make('catatan_awal')
                                        ->label('Draf / Prompt Tambahan (Opsional)')
                                        ->rows(3)
                                        ->maxLength(1500)
                                        ->placeholder('Tulis poin-poin kegiatan atau biarkan terisi dari draf deskripsi Anda saat ini agar dirapikan AI...'),
                                ])
                                ->action(function (array $data, Get $get, Set $set): void {
                                    try {
                                        $hasil = app(PenyusunDeskripsiGaleriGemini::class)->susun(
                                            (int) (auth()->id() ?? 0),
                                            (string) ($get('judul') ?? ''),
                                            (string) ($get('kategori') ?? 'Kesiswaan'),
                                            $get('tanggal_kegiatan') ? (string) $get('tanggal_kegiatan') : null,
                                            (string) ($data['catatan_awal'] ?? ''),
                                            (string) ($data['gaya'] ?? 'ramah_emoji'),
                                        );

                                        $set('deskripsi', $hasil);

                                        Notification::make()
                                            ->success()
                                            ->title('Deskripsi berhasil disusun AI')
                                            ->body('Silakan periksa kembali teks sebelum menyimpan.')
                                            ->send();
                                    } catch (Throwable $e) {
                                        Notification::make()
                                            ->danger()
                                            ->title('Gagal menyusun deskripsi AI')
                                            ->body(KlienGemini::aktif() ? $e->getMessage() : 'Gemini AI belum dikonfigurasi.')
                                            ->send();
                                    }
                                })
                        )
                        ->columnSpanFull(),
                ]),

            Section::make('Dokumentasi Foto & Video (Cloudflare R2)')
                ->compact()
                ->schema([
                    FileUpload::make('berkas')
                        ->label('Foto & Video Kegiatan')
                        ->hintIcon(Heroicon::OutlinedInformationCircle, tooltip: 'Unggah satu atau banyak foto dan video sekaligus. Seluruh file disimpan aman di Cloudflare R2.')
                        ->multiple()
                        ->reorderable()
                        ->appendFiles()
                        ->maxFiles(20)
                        ->disk(fn (): string => GaleriKegiatan::disk())
                        ->directory('galeri-kegiatan')
                        ->visibility('private')
                        ->acceptedFileTypes(static::JENIS_MEDIA)
                        ->maxSize(51200)
                        ->helperText('Mendukung foto (JPG, PNG, WebP) dan video (MP4, WebM, MOV) maksimal 50 MB per file. Maksimal 20 file per kegiatan.')
                        ->required()
                        ->columnSpanFull(),
                ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('tanggal_kegiatan', 'desc')
            ->columns([
                TextColumn::make('judul')
                    ->label('Judul Kegiatan')
                    ->searchable()
                    ->sortable()
                    ->description(fn (GaleriKegiatan $record): ?string => $record->deskripsi ? str($record->deskripsi)->limit(60)->toString() : null),

                TextColumn::make('kategori')
                    ->label('Kategori')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('tanggal_kegiatan')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('berkas')
                    ->label('Media')
                    ->formatStateUsing(function (GaleriKegiatan $record): string {
                        $files = $record->berkas ?? [];
                        $total = count($files);
                        $video = count(array_filter($files, fn (string $p): bool => GaleriKegiatan::adalahVideo($p)));
                        $foto = $total - $video;

                        $bagian = [];
                        if ($foto > 0) {
                            $bagian[] = "{$foto} foto";
                        }
                        if ($video > 0) {
                            $bagian[] = "{$video} video";
                        }

                        return $bagian !== [] ? implode(', ', $bagian) : '0 file';
                    })
                    ->badge()
                    ->color('gray'),

                TextColumn::make('komentar_count')
                    ->label('Komentar')
                    ->counts('komentar')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                ToggleColumn::make('is_publik')
                    ->label('Publik'),
            ])
            ->filters([
                SelectFilter::make('kategori')
                    ->options(GaleriKegiatan::KATEGORI),
                TernaryFilter::make('is_publik')
                    ->label('Status Publikasi')
                    ->trueLabel('Tampil di Landing Page')
                    ->falseLabel('Disembunyikan'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada dokumentasi kegiatan')
            ->emptyStateDescription('Unggah foto dan video kegiatan siswa untuk ditampilkan di halaman depan.')
            ->emptyStateIcon(Heroicon::OutlinedPhoto);
    }

    public static function getRelations(): array
    {
        return [
            KomentarRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGaleriKegiatans::route('/'),
            'create' => CreateGaleriKegiatan::route('/create'),
            'edit' => EditGaleriKegiatan::route('/{record}/edit'),
        ];
    }
}
