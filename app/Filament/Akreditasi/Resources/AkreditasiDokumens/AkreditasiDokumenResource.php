<?php

namespace App\Filament\Akreditasi\Resources\AkreditasiDokumens;

use App\Filament\Akreditasi\Resources\AkreditasiDokumens\Pages\EditAkreditasiDokumen;
use App\Filament\Akreditasi\Resources\AkreditasiDokumens\Pages\ListAkreditasiDokumens;
use App\Models\AkreditasiDokumen;
use App\Models\PenandaTangan;
use App\Models\TahunAjaran;
use App\Services\Akreditasi\PenyusunDokumenAkreditasiAi;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AkreditasiDokumenResource extends Resource
{
    protected static ?string $model = AkreditasiDokumen::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Dokumen & DKA';

    protected static ?string $pluralModelLabel = 'Dokumen Wajib & DKA';

    protected static ?string $slug = 'dokumen';

    public static function getNavigationBadge(): ?string
    {
        $total = static::getModel()::query()->where('status', '!=', 'belum')->count();

        return $total > 0 ? "{$total}/20" : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Identitas Dokumen')
                    ->columns(3)
                    ->schema([
                        Select::make('tahun_ajaran_id')
                            ->label('Tahun Ajaran')
                            ->options(fn (): array => TahunAjaran::query()->orderByDesc('nama')->pluck('nama', 'id')->all())
                            ->disabled()
                            ->dehydrated(),
                        TextInput::make('kode')
                            ->label('Kode')
                            ->disabled()
                            ->dehydrated(),
                        Select::make('status')
                            ->options([
                                'belum' => 'Belum Disusun',
                                'draf' => 'Draf',
                                'final' => 'Final / Siap Visitasi',
                            ])
                            ->required(),
                        TextInput::make('judul')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Section::make('Isi Dokumen / Narasi DKA')
                    ->schema([
                        RichEditor::make('isi_html')
                            ->label('Konten Dokumen')
                            ->columnSpanFull(),
                    ]),

                Section::make('Pengesahan (3 Penanda Tangan)')
                    ->columns(3)
                    ->schema([
                        Select::make('kepala_madrasah_id')
                            ->label('Kepala Madrasah')
                            ->options(fn (): array => PenandaTangan::query()->where('is_aktif', true)->orderBy('urutan')->pluck('nama', 'id')->all())
                            ->searchable(),
                        Select::make('ketua_tim_id')
                            ->label('Ketua Tim / Komite')
                            ->options(fn (): array => PenandaTangan::query()->where('is_aktif', true)->orderBy('urutan')->pluck('nama', 'id')->all())
                            ->searchable(),
                        TextInput::make('jabatan_pengawas')
                            ->label('Jabatan Pengawas / Kemenag')
                            ->placeholder('Pengawas Madrasah')
                            ->maxLength(255),
                        TextInput::make('nama_pengawas')
                            ->label('Nama Pengawas / Kemenag')
                            ->maxLength(255),
                        TextInput::make('nip_pengawas')
                            ->label('NIP Pengawas')
                            ->maxLength(30),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                TextColumn::make('kode')
                    ->label('Kode')
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->badge()
                    ->sortable(),
                TextColumn::make('kategori')
                    ->label('Lapisan')
                    ->formatStateUsing(fn (string $state): string => $state === 'dokumen_wajib' ? 'Dokumen Wajib' : 'DKA 14 Butir')
                    ->badge()
                    ->icon(fn (string $state): Heroicon => $state === 'dokumen_wajib' ? Heroicon::OutlinedDocumentCheck : Heroicon::OutlinedClipboardDocumentList)
                    ->color(fn (string $state): string => $state === 'dokumen_wajib' ? 'info' : 'gray'),
                TextColumn::make('judul')
                    ->searchable()
                    ->wrap()
                    ->weight('bold'),
                TextColumn::make('status')
                    ->badge()
                    ->icon(fn (string $state): Heroicon => match ($state) {
                        'final' => Heroicon::OutlinedCheckCircle,
                        'draf' => Heroicon::OutlinedPencilSquare,
                        default => Heroicon::OutlinedClock,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'final' => 'success',
                        'draf' => 'warning',
                        default => 'gray',
                    }),
                IconColumn::make('dibantu_ai')
                    ->label('AI')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('kategori')
                    ->options([
                        'dokumen_wajib' => '6 Dokumen Wajib',
                        'dka_butir' => 'DKA 14 Butir',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'belum' => 'Belum Disusun',
                        'draf' => 'Draf',
                        'final' => 'Final',
                    ]),
            ])
            ->recordActions([
                Action::make('generateAi')
                    ->label('Generate AI')
                    ->icon(Heroicon::Sparkles)
                    ->extraAttributes(['class' => 'btn-ai-generate'])
                    ->visible(fn (AkreditasiDokumen $record): bool => auth()->user()?->can('Update:AkreditasiDokumen') ?? false)
                    ->action(function (AkreditasiDokumen $record): void {
                        app(PenyusunDokumenAkreditasiAi::class)->susun($record);
                        Notification::make()->success()->title("Draf {$record->judul} berhasil disusun")->send();
                    }),
                Action::make('cetakPdf')
                    ->label('Cetak PDF')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('gray')
                    ->url(fn (AkreditasiDokumen $record): string => route('akreditasi.dokumen.cetak', $record))
                    ->openUrlInNewTab(),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAkreditasiDokumens::route('/'),
            'edit' => EditAkreditasiDokumen::route('/{record}/edit'),
        ];
    }
}
