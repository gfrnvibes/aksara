<?php

namespace App\Filament\Guru\Pages;

use App\Models\Gtk;
use App\Models\JurnalMengajar as Jurnal;
use App\Services\Gemini\KlienGemini;
use App\Services\Harian\AksesHarian;
use App\Services\Harian\PenyusunJurnalGemini;
use App\Services\Harian\PertemuanMengajar;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Attributes\Url;
use Throwable;

class JurnalMengajar extends HalamanHarian implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $title = 'Jurnal Mengajar';

    protected static ?int $navigationSort = 2;

    #[Url]
    public string $tanggal = '';

    public function mount(): void
    {
        $this->tanggal = $this->tanggal ?: AksesHarian::hariIni();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function getSubheading(): ?string
    {
        return 'Pertemuan dari jadwal terbit. Catatan AI berupa draf; periksa sebelum disimpan. Satu jurnal untuk satu rangkaian jam pelajaran.';
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('tanggal')->label(fn (): string => 'Tanggal: '.$this->tanggal)->color('gray')
            ->schema([DatePicker::make('tanggal')->default(fn () => $this->tanggal)->required()->maxDate(AksesHarian::hariIni())])
            ->action(function (array $data): void {
                $this->tanggal = $data['tanggal'];
                $this->resetTable();
            })];
    }

    public function table(Table $table): Table
    {
        return $table->records(fn () => app(PertemuanMengajar::class)->daftar(static::user(), $this->tanggal, true))
            ->defaultPaginationPageOption(25)->columns([
                TextColumn::make('nama_kelas')->label('Kelas'), TextColumn::make('nama_mapel')->label('Mata pelajaran'),
                TextColumn::make('nama_guru')->label('Guru jadwal'),
                TextColumn::make('jp_mulai')->label('Jam')->formatStateUsing(fn (array $record): string => 'JP '.$record['jp_mulai'].'–'.$record['jp_selesai'])
                    ->description(fn (array $record): ?string => $record['waktu']),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state): string => Jurnal::STATUS[$state] ?? 'Belum dicatat')
                    ->color(fn (string $state): string => match ($state) {
                        'terlaksana' => 'success', 'tidak_terlaksana' => 'danger', default => 'warning'
                    }),
                TextColumn::make('materi')->label('Materi')->limit(60)->wrap(),
            ])->recordActions([
                Action::make('hadir')->label('Hadir mengajar')->icon(Heroicon::OutlinedCheck)
                    ->visible(fn (array $record): bool => $record['status'] === 'belum_dicatat' && (int) $record['guru_id'] === static::user()->gtk?->id)
                    ->schema(fn (): array => $this->tanggal === AksesHarian::hariIni() ? [] : [Textarea::make('alasan')->label('Alasan pencatatan tanggal lampau')->required()->maxLength(500)])
                    ->action(function (array $record, array $data): void {
                        app(PertemuanMengajar::class)->simpan(static::user(), $this->tanggal, $record['sumber_jadwal'], $data, true);
                        Notification::make()->success()->title('Kehadiran tercatat. Lengkapi jurnal saat selesai mengajar.')->send();
                    }),
                Action::make('isi')->label('Isi / koreksi jurnal')->icon(Heroicon::OutlinedPencilSquare)
                    ->fillForm(fn (array $record): array => [...$record, 'status' => in_array($record['status'], ['belum_dicatat', 'hadir']) ? 'terlaksana' : $record['status']])
                    ->schema([
                        Select::make('status')->options(fn (): array => AksesHarian::piket(static::user(), $this->tanggal) ? array_diff_key(Jurnal::STATUS, ['hadir' => true]) : ['terlaksana' => 'Terlaksana', 'kegiatan_pengganti' => 'Kegiatan pengganti'])->required()->live(),
                        Select::make('pelaksana_id')->label('Guru pengganti')->options(fn (): array => Gtk::query()->aktif()->orderBy('nama')->pluck('nama', 'id')->all())->searchable()->visible(fn (Get $get): bool => $get('status') === 'digantikan')->required(fn (Get $get): bool => $get('status') === 'digantikan'),
                        Textarea::make('catatan_asli')->label('Catatan singkat Anda')->rows(3)->maxLength(3000)
                            ->helperText('Tuliskan yang benar-benar dilaksanakan. Jangan masukkan identitas atau informasi pribadi siswa.')
                            ->hintAction(Action::make('rapikan')->label('Rapikan dengan Gemini')->visible(fn (): bool => KlienGemini::aktif())
                                ->action(function (array $record, Get $get, Set $set): void {
                                    abort_unless(app(PertemuanMengajar::class)->daftar(static::user(), $this->tanggal, true)->has($record['sumber_jadwal']), 403);
                                    try {
                                        $result = app(PenyusunJurnalGemini::class)->susun(static::user()->id, (int) $record['kelas_id'], $record['nama_mapel'], (string) $get('catatan_asli'));
                                        foreach ($result as $key => $value) {
                                            $set($key, $value);
                                        }
                                        $set('dibantu_ai', true);
                                        Notification::make()->success()->title('Draf dirapikan. Periksa sebelum menyimpan.')->send();
                                    } catch (Throwable $e) {
                                        Notification::make()->danger()->title('Draf AI belum dapat dibuat')->body(KlienGemini::alasanGagal($e))->send();
                                    }
                                })),
                        Textarea::make('materi')->label('Materi')->rows(2)->maxLength(3000),
                        Textarea::make('kegiatan')->label('Kegiatan pembelajaran')->rows(3)->maxLength(5000),
                        Textarea::make('refleksi')->rows(2)->maxLength(3000),
                        Textarea::make('tindak_lanjut')->label('Tindak lanjut / usulan')->rows(2)->maxLength(3000),
                        Textarea::make('alasan')->label('Alasan koreksi / penggantian / tanggal lampau')->maxLength(500),
                        Hidden::make('dibantu_ai')->default(false),
                    ])->action(function (array $record, array $data): void {
                        app(PertemuanMengajar::class)->simpan(static::user(), $this->tanggal, $record['sumber_jadwal'], $data);
                        Notification::make()->success()->title('Jurnal tersimpan')->send();
                    }),
            ])->emptyStateHeading('Tidak ada pertemuan pada tanggal ini')->emptyStateDescription('Periksa jadwal terbit, tahun ajaran, serta kalender libur.');
    }
}
