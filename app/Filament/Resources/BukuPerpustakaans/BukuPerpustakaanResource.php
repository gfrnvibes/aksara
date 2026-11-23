<?php

namespace App\Filament\Resources\BukuPerpustakaans;

use App\Filament\Resources\BukuPerpustakaans\Pages\ManageBukuPerpustakaans;
use App\Models\BukuPerpustakaan;
use App\Models\MataPelajaran;
use App\Services\Perpustakaan\CariMetadataIsbn;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class BukuPerpustakaanResource extends Resource
{
    protected static ?string $model = BukuPerpustakaan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Informasi';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Buku Digital';

    protected static ?string $pluralModelLabel = 'Perpustakaan Digital';

    protected static ?string $slug = 'perpustakaan-digital';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('isbn')
                    ->label('ISBN (Opsional)')
                    ->maxLength(30)
                    ->placeholder('Contoh: 9786022446125')
                    ->suffixAction(
                        Action::make('cariIsbn')
                            ->label('Cari ISBN')
                            ->icon(Heroicon::OutlinedMagnifyingGlass)
                            ->action(function (Get $get, Set $set): void {
                                $isbn = (string) ($get('isbn') ?? '');
                                if (trim($isbn) === '') {
                                    Notification::make()
                                        ->warning()
                                        ->title('Masukkan ISBN terlebih dahulu')
                                        ->send();

                                    return;
                                }

                                $meta = app(CariMetadataIsbn::class)->cari($isbn);
                                if ($meta === null) {
                                    Notification::make()
                                        ->warning()
                                        ->title('Metadata ISBN tidak ditemukan')
                                        ->body('Silakan isi judul dan detail buku secara manual.')
                                        ->send();

                                    return;
                                }

                                if (filled($meta['judul'])) {
                                    $set('judul', $meta['judul']);
                                }
                                if (filled($meta['penulis'])) {
                                    $set('penulis', $meta['penulis']);
                                }
                                if (filled($meta['penerbit'])) {
                                    $set('penerbit', $meta['penerbit']);
                                }
                                if ($meta['tahun_terbit'] !== null) {
                                    $set('tahun_terbit', $meta['tahun_terbit']);
                                }
                                if (filled($meta['deskripsi'])) {
                                    $set('deskripsi', $meta['deskripsi']);
                                }

                                Notification::make()
                                    ->success()
                                    ->title('Metadata buku berhasil diisi dari ISBN')
                                    ->send();
                            }),
                    ),
                Select::make('kategori')
                    ->label('Kategori')
                    ->options(BukuPerpustakaan::KATEGORI)
                    ->default('paket')
                    ->required(),
                Select::make('sumber')
                    ->label('Sumber Buku')
                    ->options(BukuPerpustakaan::SUMBER)
                    ->default('manual')
                    ->required(),
                TextInput::make('judul')
                    ->label('Judul Buku')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('penulis')
                    ->label('Penulis / Penyusun')
                    ->maxLength(255),
                TextInput::make('penerbit')
                    ->label('Penerbit')
                    ->maxLength(255),
                TextInput::make('tahun_terbit')
                    ->label('Tahun Terbit')
                    ->numeric()
                    ->integer()
                    ->minValue(1900)
                    ->maxValue(2100),
                Select::make('tingkat')
                    ->label('Tingkat Kelas')
                    ->options(BukuPerpustakaan::TINGKAT)
                    ->placeholder('Semua Tingkat'),
                Select::make('mata_pelajaran_id')
                    ->label('Mata Pelajaran Terkait')
                    ->options(fn (): array => MataPelajaran::query()->orderBy('nama')->pluck('nama', 'id')->all())
                    ->searchable()
                    ->placeholder('Umum / Tidak spesifik'),
                Toggle::make('is_aktif')
                    ->label('Tampilkan ke Publik & Siswa')
                    ->default(true)
                    ->inline(false),
                FileUpload::make('file_pdf')
                    ->label('File E-Book (PDF)')
                    ->disk(fn (): string => BukuPerpustakaan::diskName())
                    ->directory('perpustakaan/pdf')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(51200)
                    ->required(fn (Get $get): bool => blank($get('sumber_url')))
                    ->helperText('Unggah langsung file PDF, atau isi URL PDF Resmi di bawah untuk diunduh ke storage madrasah.'),
                TextInput::make('sumber_url')
                    ->label('URL PDF Resmi (Opsional)')
                    ->url()
                    ->maxLength(500)
                    ->placeholder('https://buku.kemdikbud.go.id/.../buku.pdf')
                    ->helperText('Jika diisi dan file belum diunggah, Aksara otomatis mengunduh PDF sekali ke storage lokal.'),
                FileUpload::make('sampul')
                    ->label('Sampul Buku (Opsional)')
                    ->disk(fn (): string => BukuPerpustakaan::sampulDiskName())
                    ->directory('perpustakaan/sampul')
                    ->image(),
                Textarea::make('deskripsi')
                    ->label('Deskripsi / Ringkasan')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('judul')
                    ->label('Judul Buku')
                    ->searchable()
                    ->sortable()
                    ->limit(45),
                TextColumn::make('kategori')
                    ->label('Kategori')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => BukuPerpustakaan::KATEGORI[$state] ?? ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'paket' => 'success',
                        'pai' => 'warning',
                        'literasi' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('sumber')
                    ->label('Sumber')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => BukuPerpustakaan::SUMBER[$state] ?? ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'sibi' => 'info',
                        'kemenag' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('tingkat')
                    ->label('Kelas')
                    ->formatStateUsing(fn (?int $state): string => $state !== null ? (BukuPerpustakaan::TINGKAT[$state] ?? "Kelas {$state}") : 'Semua')
                    ->placeholder('Semua'),
                TextColumn::make('mataPelajaran.nama')
                    ->label('Mapel')
                    ->placeholder('Umum'),
                TextColumn::make('penulis')
                    ->label('Penulis')
                    ->searchable()
                    ->placeholder('-')
                    ->limit(25),
                TextColumn::make('jumlah_dibaca')
                    ->label('Dibaca')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_aktif')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('kategori')
                    ->label('Kategori')
                    ->options(BukuPerpustakaan::KATEGORI),
                SelectFilter::make('sumber')
                    ->label('Sumber')
                    ->options(BukuPerpustakaan::SUMBER),
                SelectFilter::make('tingkat')
                    ->label('Tingkat Kelas')
                    ->options(BukuPerpustakaan::TINGKAT),
                TernaryFilter::make('is_aktif')
                    ->label('Status Aktif'),
            ])
            ->recordActions([
                Action::make('baca')
                    ->label('Baca')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (BukuPerpustakaan $record): string => route('perpustakaan.baca', $record), shouldOpenInNewTab: true)
                    ->visible(fn (BukuPerpustakaan $record): bool => $record->is_aktif),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBukuPerpustakaans::route('/'),
        ];
    }
}
