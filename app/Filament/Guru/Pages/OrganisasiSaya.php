<?php

namespace App\Filament\Guru\Pages;

use App\Enums\Semester;
use App\Filament\Resources\Rapors\Schemas\RaporForm;
use App\Models\AnggotaOrganisasi;
use App\Models\Ekstrakurikuler;
use App\Models\Gtk;
use App\Models\KegiatanOrganisasi;
use App\Models\NilaiEkstrakurikuler;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Workspace in the teacher panel for advisors of student organizations or extracurriculars.
 *
 * @property-read Schema $form
 */
class OrganisasiSaya extends Page implements HasSchemas, HasTable
{
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Organisasi & Ekskul';

    protected static ?string $title = 'Organisasi & Ekstrakurikuler Saya';

    protected static ?string $slug = 'organisasi-saya';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return static::gtk()?->ekskulBinaan()->aktif()->exists() ?? false;
    }

    protected static function gtk(): ?Gtk
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user->gtk : null;
    }

    public function mount(): void
    {
        $this->form->fill([
            'ekstrakurikuler_id' => static::gtk()?->ekskulBinaan()->aktif()->orderBy('nama')->value('id'),
            'tahun_ajaran_id' => TahunAjaran::query()->where('is_aktif', true)->value('id'),
            'semester' => Semester::berdasarkanTanggal(now())->value,
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedSchema::make('form'),
            EmbeddedTable::make(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make()
                    ->columns(['default' => 1, 'sm' => 3])
                    ->schema([
                        Select::make('ekstrakurikuler_id')
                            ->label('Organisasi / Ekstrakurikuler')
                            ->options(fn (): array => static::gtk()?->ekskulBinaan()->aktif()->orderBy('nama')->pluck('nama', 'id')->all() ?? [])
                            ->selectablePlaceholder(false)
                            ->live()
                            ->afterStateUpdated(fn () => $this->resetTable()),
                        Select::make('tahun_ajaran_id')
                            ->label('Tahun Ajaran')
                            ->options(fn (): array => TahunAjaran::query()->orderByDesc('nama')->pluck('nama', 'id')->all())
                            ->selectablePlaceholder(false)
                            ->live()
                            ->afterStateUpdated(fn () => $this->resetTable()),
                        Select::make('semester')
                            ->label('Semester')
                            ->options(Semester::class)
                            ->selectablePlaceholder(false)
                            ->live()
                            ->afterStateUpdated(fn () => $this->resetTable()),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('tambahAnggota')
                ->label('Tambah Anggota')
                ->icon(Heroicon::OutlinedUserPlus)
                ->visible(fn (): bool => $this->ekskulTerpilih() !== null)
                ->schema([
                    Select::make('siswa_id')
                        ->label('Siswa')
                        ->options(fn (): array => Siswa::query()
                            ->whereNotIn('id', AnggotaOrganisasi::query()
                                ->where('ekstrakurikuler_id', $this->ekskulId())
                                ->where('tahun_ajaran_id', $this->tahunAjaranId())
                                ->select('siswa_id'))
                            ->orderBy('nama_lengkap')
                            ->get()
                            ->mapWithKeys(fn (Siswa $s): array => [$s->id => $s->nis.' - '.$s->nama_lengkap])
                            ->all())
                        ->searchable()
                        ->required(),
                    Select::make('jabatan')
                        ->label('Jabatan')
                        ->options([
                            'Ketua' => 'Ketua',
                            'Wakil Ketua' => 'Wakil Ketua',
                            'Sekretaris' => 'Sekretaris',
                            'Bendahara' => 'Bendahara',
                            'Koordinator' => 'Koordinator',
                            'Anggota' => 'Anggota',
                        ])
                        ->default('Anggota')
                        ->required(),
                    TextInput::make('catatan')
                        ->label('Catatan')
                        ->maxLength(255),
                ])
                ->action(function (array $data): void {
                    $ekskul = $this->ekskulTerpilih();
                    abort_if($ekskul === null, 403);

                    AnggotaOrganisasi::query()->create([
                        'ekstrakurikuler_id' => $ekskul->id,
                        'tahun_ajaran_id' => $this->tahunAjaranId(),
                        'siswa_id' => $data['siswa_id'],
                        'jabatan' => $data['jabatan'],
                        'catatan' => $data['catatan'] ?? null,
                    ]);

                    Notification::make()->success()->title('Anggota ditambahkan')->send();
                }),
            Action::make('catatKegiatan')
                ->label('Catat Kegiatan')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->color('gray')
                ->visible(fn (): bool => $this->ekskulTerpilih() !== null)
                ->schema([
                    DatePicker::make('tanggal')
                        ->label('Tanggal')
                        ->default(today())
                        ->maxDate(today())
                        ->required(),
                    TextInput::make('materi')
                        ->label('Materi / Agenda Kegiatan')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('jumlah_hadir')
                        ->label('Jumlah Anggota Hadir')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->default(fn (): int => AnggotaOrganisasi::query()
                            ->where('ekstrakurikuler_id', $this->ekskulId())
                            ->where('tahun_ajaran_id', $this->tahunAjaranId())
                            ->count())
                        ->required(),
                    Textarea::make('catatan')
                        ->label('Catatan Kegiatan')
                        ->rows(3),
                ])
                ->action(function (array $data): void {
                    $ekskul = $this->ekskulTerpilih();
                    $gtk = static::gtk();
                    abort_if($ekskul === null || $gtk === null, 403);

                    KegiatanOrganisasi::query()->create([
                        'ekstrakurikuler_id' => $ekskul->id,
                        'tahun_ajaran_id' => $this->tahunAjaranId(),
                        'pembina_id' => $gtk->id,
                        'tanggal' => $data['tanggal'],
                        'materi' => $data['materi'],
                        'jumlah_hadir' => $data['jumlah_hadir'],
                        'catatan' => $data['catatan'] ?? null,
                    ]);

                    Notification::make()->success()->title('Jurnal kegiatan tersimpan')->send();
                }),
            Action::make('riwayatKegiatan')
                ->label('Riwayat Kegiatan')
                ->icon(Heroicon::OutlinedClock)
                ->color('gray')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup')
                ->visible(fn (): bool => $this->ekskulTerpilih() !== null)
                ->infolist([
                    RepeatableEntry::make('kegiatan')
                        ->hiddenLabel()
                        ->state(fn (): array => KegiatanOrganisasi::query()
                            ->where('ekstrakurikuler_id', $this->ekskulId())
                            ->where('tahun_ajaran_id', $this->tahunAjaranId())
                            ->orderByDesc('tanggal')
                            ->limit(15)
                            ->get()
                            ->map(fn (KegiatanOrganisasi $k): array => [
                                'tanggal' => $k->tanggal->translatedFormat('d M Y'),
                                'materi' => $k->materi,
                                'jumlah_hadir' => $k->jumlah_hadir.' hadir',
                            ])
                            ->all())
                        ->columns(3)
                        ->schema([
                            TextEntry::make('tanggal')->label('Tanggal'),
                            TextEntry::make('materi')->label('Agenda'),
                            TextEntry::make('jumlah_hadir')->label('Kehadiran'),
                        ]),
                ]),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => AnggotaOrganisasi::query()
                ->with(['siswa.kelas'])
                ->where('ekstrakurikuler_id', $this->ekskulId())
                ->where('tahun_ajaran_id', $this->tahunAjaranId()))
            ->defaultSort('jabatan')
            ->paginated(false)
            ->emptyStateHeading('Belum ada anggota terdaftar')
            ->emptyStateDescription('Gunakan tombol Tambah Anggota untuk mendaftarkan siswa.')
            ->columns([
                TextColumn::make('siswa.nis')
                    ->label('NIS'),
                TextColumn::make('siswa.nama_lengkap')
                    ->label('Nama Siswa')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('siswa.kelas.nama')
                    ->label('Kelas')
                    ->placeholder('-'),
                TextColumn::make('jabatan')
                    ->label('Jabatan')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Anggota' ? 'gray' : 'primary'),
                TextColumn::make('nilai_rapor')
                    ->label('Nilai Ekskul')
                    ->state(function (AnggotaOrganisasi $record): string {
                        $nilai = $this->nilaiEkskulUntuk($record);

                        return $nilai !== null && filled($nilai->nilai) ? $nilai->nilai : '-';
                    })
                    ->badge()
                    ->color(fn (string $state): string => $state === '-' ? 'gray' : 'success'),
                TextColumn::make('catatan')
                    ->label('Catatan')
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->recordActions([
                Action::make('ubahJabatan')
                    ->label('Jabatan')
                    ->icon(Heroicon::OutlinedTag)
                    ->fillForm(fn (AnggotaOrganisasi $record): array => [
                        'jabatan' => $record->jabatan,
                        'catatan' => $record->catatan,
                    ])
                    ->schema([
                        Select::make('jabatan')
                            ->options([
                                'Ketua' => 'Ketua',
                                'Wakil Ketua' => 'Wakil Ketua',
                                'Sekretaris' => 'Sekretaris',
                                'Bendahara' => 'Bendahara',
                                'Koordinator' => 'Koordinator',
                                'Anggota' => 'Anggota',
                            ])
                            ->required(),
                        TextInput::make('catatan')->maxLength(255),
                    ])
                    ->action(function (AnggotaOrganisasi $record, array $data): void {
                        abort_if($this->ekskulTerpilih()?->id !== $record->ekstrakurikuler_id, 403);
                        $record->update($data);
                        Notification::make()->success()->title('Jabatan diperbarui')->send();
                    }),
                Action::make('nilaiRapor')
                    ->label('Nilai Rapor')
                    ->icon(Heroicon::OutlinedAcademicCap)
                    ->fillForm(function (AnggotaOrganisasi $record): array {
                        $nilai = $this->nilaiEkskulUntuk($record);

                        return [
                            'nilai' => $nilai?->nilai,
                            'keterangan' => $nilai?->keterangan,
                        ];
                    })
                    ->schema([
                        Select::make('nilai')
                            ->label('Predikat')
                            ->options(fn (): array => RaporForm::pilihanNilaiEkstrakurikuler(TahunAjaran::skemaUntuk($this->tahunAjaranId())))
                            ->required(),
                        Textarea::make('keterangan')
                            ->label('Keterangan / Deskripsi')
                            ->rows(3),
                    ])
                    ->action(function (AnggotaOrganisasi $record, array $data): void {
                        abort_if($this->ekskulTerpilih()?->id !== $record->ekstrakurikuler_id, 403);

                        $kelasId = $record->siswa->kelas_id;
                        if ($kelasId === null) {
                            Notification::make()->danger()->title('Siswa belum memiliki kelas aktif')->send();

                            return;
                        }

                        $rapor = Rapor::query()->firstOrCreate(
                            [
                                'siswa_id' => $record->siswa_id,
                                'tahun_ajaran_id' => $this->tahunAjaranId(),
                                'semester' => $this->semesterTerpilih(),
                            ],
                            [
                                'kelas_id' => $kelasId,
                            ],
                        );

                        NilaiEkstrakurikuler::query()->updateOrCreate(
                            [
                                'rapor_id' => $rapor->id,
                                'ekstrakurikuler_id' => $record->ekstrakurikuler_id,
                            ],
                            [
                                'nilai' => $data['nilai'],
                                'keterangan' => $data['keterangan'] ?? null,
                            ],
                        );

                        Notification::make()->success()->title('Nilai ekstrakurikuler tersimpan ke rapor')->send();
                    }),
                DeleteAction::make()
                    ->label('Keluarkan')
                    ->visible(fn (AnggotaOrganisasi $record): bool => $this->ekskulTerpilih()?->id === $record->ekstrakurikuler_id),
            ]);
    }

    protected function ekskulId(): int
    {
        return (int) ($this->data['ekstrakurikuler_id'] ?? 0);
    }

    protected function tahunAjaranId(): int
    {
        return (int) ($this->data['tahun_ajaran_id'] ?? 0);
    }

    protected function semesterTerpilih(): Semester
    {
        return Semester::tryFrom((string) ($this->data['semester'] ?? '')) ?? Semester::berdasarkanTanggal(now());
    }

    protected function ekskulTerpilih(): ?Ekstrakurikuler
    {
        $gtk = static::gtk();
        $id = $this->ekskulId();
        if ($gtk === null || $id === 0) {
            return null;
        }

        return Ekstrakurikuler::query()
            ->aktif()
            ->where('pembina_id', $gtk->id)
            ->whereKey($id)
            ->first();
    }

    protected function nilaiEkskulUntuk(AnggotaOrganisasi $record): ?NilaiEkstrakurikuler
    {
        $raporId = Rapor::query()
            ->where('siswa_id', $record->siswa_id)
            ->where('tahun_ajaran_id', $this->tahunAjaranId())
            ->where('semester', $this->semesterTerpilih())
            ->value('id');

        if ($raporId === null) {
            return null;
        }

        return NilaiEkstrakurikuler::query()
            ->where('rapor_id', $raporId)
            ->where('ekstrakurikuler_id', $record->ekstrakurikuler_id)
            ->first();
    }
}
