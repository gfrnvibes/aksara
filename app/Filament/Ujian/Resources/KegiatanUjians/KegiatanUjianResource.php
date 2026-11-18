<?php

namespace App\Filament\Ujian\Resources\KegiatanUjians;

use App\Enums\Semester;
use App\Filament\Ujian\Resources\KegiatanUjians\Pages\CreateKegiatanUjian;
use App\Filament\Ujian\Resources\KegiatanUjians\Pages\EditKegiatanUjian;
use App\Filament\Ujian\Resources\KegiatanUjians\Pages\KelolaRuangDanDenah;
use App\Filament\Ujian\Resources\KegiatanUjians\Pages\ListKegiatanUjians;
use App\Models\KegiatanUjian;
use App\Models\KopSurat;
use App\Models\PenandaTangan;
use App\Models\TahunAjaran;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class KegiatanUjianResource extends Resource
{
    protected static ?string $model = KegiatanUjian::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Kartu & Denah Ujian';

    protected static ?string $pluralModelLabel = 'Kartu & Denah Ujian';

    protected static ?string $slug = 'kartu-denah';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Identitas Kegiatan Ujian')
                    ->columns(3)
                    ->schema([
                        TextInput::make('nama')
                            ->label('Nama Kegiatan')
                            ->datalist(['Penilaian Tengah Semester', 'Penilaian Akhir Semester', 'Penilaian Akhir Tahun', 'Asesmen Madrasah'])
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        Select::make('tahun_ajaran_id')
                            ->label('Tahun Ajaran')
                            ->options(fn (): array => TahunAjaran::query()->orderByDesc('nama')->pluck('nama', 'id')->all())
                            ->default(fn (): ?int => TahunAjaran::query()->where('is_aktif', true)->value('id'))
                            ->required(),
                        Select::make('semester')
                            ->options(Semester::class)
                            ->default(Semester::berdasarkanTanggal(now()))
                            ->required(),
                        DatePicker::make('tanggal_mulai')
                            ->label('Tanggal Mulai'),
                        DatePicker::make('tanggal_selesai')
                            ->label('Tanggal Selesai'),
                        TextInput::make('prefix_nomor_peserta')
                            ->label('Prefix Nomor Peserta')
                            ->placeholder('26-')
                            ->helperText('Contoh hasil: 26-07-001. Kosongkan untuk otomatis dari tahun ajaran.')
                            ->maxLength(30),
                        Select::make('penanda_tangan_id')
                            ->label('Kepala Madrasah')
                            ->options(fn (): array => PenandaTangan::query()->where('is_aktif', true)->orderBy('urutan')->pluck('nama', 'id')->all())
                            ->searchable(),
                        Select::make('ketua_panitia_id')
                            ->label('Ketua Panitia Ujian')
                            ->options(fn (): array => PenandaTangan::query()->where('is_aktif', true)->orderBy('urutan')->pluck('nama', 'id')->all())
                            ->searchable(),
                        Select::make('kop_surat_id')
                            ->label('Kop Madrasah (Denah)')
                            ->options(fn (): array => KopSurat::query()->orderBy('nama')->pluck('nama', 'id')->all())
                            ->searchable(),
                        Textarea::make('catatan_kartu')
                            ->label('Catatan Singkat di Kartu')
                            ->placeholder('Harap membawa kartu ini selama ujian berlangsung.')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('nama')
                    ->label('Kegiatan Ujian')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('tahunAjaran.nama')
                    ->label('Tahun Ajaran'),
                TextColumn::make('semester')
                    ->badge(),
                TextColumn::make('ruang_count')
                    ->label('Ruang')
                    ->counts('ruang'),
                TextColumn::make('penempatan_count')
                    ->label('Peserta')
                    ->counts('penempatan'),
            ])
            ->recordActions([
                Action::make('kelolaDenah')
                    ->label('Ruang & Denah')
                    ->icon(Heroicon::OutlinedSquares2x2)
                    ->color('primary')
                    ->url(fn (KegiatanUjian $record): string => static::getUrl('denah', ['record' => $record])),
                ActionGroup::make([
                    Action::make('cetakKartu')
                        ->label('Cetak Semua Kartu (A4)')
                        ->icon(Heroicon::OutlinedIdentification)
                        ->url(fn (KegiatanUjian $record): string => route('ujian.kartu', $record))
                        ->openUrlInNewTab()
                        ->visible(fn (KegiatanUjian $record): bool => $record->penempatan()->exists()),
                    Action::make('cetakDenah')
                        ->label('Cetak Denah Ruang (A4)')
                        ->icon(Heroicon::OutlinedPrinter)
                        ->url(fn (KegiatanUjian $record): string => route('ujian.denah', $record))
                        ->openUrlInNewTab()
                        ->visible(fn (KegiatanUjian $record): bool => $record->ruang()->exists()),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada kegiatan ujian')
            ->emptyStateDescription('Buat kegiatan ujian untuk mengatur ruang, memetakan denah tempat duduk, dan mencetak kartu ujian sekaligus.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKegiatanUjians::route('/'),
            'create' => CreateKegiatanUjian::route('/create'),
            'edit' => EditKegiatanUjian::route('/{record}/edit'),
            'denah' => KelolaRuangDanDenah::route('/{record}/denah'),
        ];
    }
}
