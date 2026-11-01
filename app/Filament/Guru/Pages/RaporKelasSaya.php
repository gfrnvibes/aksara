<?php

namespace App\Filament\Guru\Pages;

use App\Enums\KeputusanKenaikan;
use App\Enums\Semester;
use App\Filament\Resources\Rapors\Schemas\RaporForm;
use App\Models\Ekstrakurikuler;
use App\Models\Gtk;
use App\Models\Kelas;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

/**
 * The homeroom teacher's report cards: one row per student of their class this academic year, where they fill in
 * attendance, attitude, activities, achievements and the homeroom note, and print the report card. Subject grades
 * are entered by each subject's teacher.
 */
class RaporKelasSaya extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Rapor Kelas Saya';

    protected static ?string $title = 'Rapor Kelas Saya';

    protected static ?string $slug = 'rapor-kelas-saya';

    public static function canAccess(): bool
    {
        return static::gtk()?->kelasPerwalian()->exists() ?? false;
    }

    protected static function gtk(): ?Gtk
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user->gtk : null;
    }

    /**
     * The class a link opens the page on (?kelas=..), when the teacher looks after more than one.
     */
    #[Url]
    public ?int $kelas = null;

    public function mount(): void
    {
        if ($this->kelas !== null) {
            $this->tableFilters = ['kelas' => ['value' => $this->kelas]];
        }
    }

    public function getSubheading(): ?string
    {
        $tahunAjaran = $this->tahunAjaran();

        return $tahunAjaran === null
            ? 'Belum ada tahun ajaran aktif.'
            : "Tahun ajaran {$tahunAjaran->nama}. Nilai mata pelajaran diisi oleh guru mapel masing-masing.";
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->tahunAjaran() === null || $this->kelasTerpilih() === null
                ? Siswa::query()->whereRaw('1 = 0')
                : Siswa::query()
                    ->anggotaKelas($this->kelasTerpilih()->id, $this->tahunAjaran()->id)
                    ->with(['rapor' => fn ($rapor) => $rapor
                        ->withTrashed()
                        ->where('tahun_ajaran_id', $this->tahunAjaran()->id)
                        ->where('semester', $this->semesterTerpilih())
                        ->withCount('nilaiRapor')]))
            ->defaultSort('nama_lengkap')
            ->paginated(false)
            ->filters([
                SelectFilter::make('kelas')
                    ->label('Kelas')
                    ->options(fn (): array => static::gtk()?->kelasPerwalian()->orderBy('tingkat')->orderBy('nama')->pluck('nama', 'id')->all() ?? [])
                    ->default(fn (): ?int => static::gtk()?->kelasPerwalian()->orderBy('tingkat')->orderBy('nama')->value('id'))
                    ->selectablePlaceholder(false)
                    ->query(fn (Builder $query): Builder => $query),
                SelectFilter::make('semester')
                    ->options(Semester::class)
                    ->default(Semester::berdasarkanTanggal(now())->value)
                    ->selectablePlaceholder(false)
                    ->query(fn (Builder $query): Builder => $query),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->emptyStateHeading('Belum ada siswa di kelas ini')
            ->columns([
                TextColumn::make('nis')
                    ->label('NIS'),
                TextColumn::make('nama_lengkap')
                    ->label('Nama Siswa')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('nilai_terisi')
                    ->label('Mapel Dinilai')
                    ->state(fn (Siswa $record): int => $this->raporDari($record)->nilai_rapor_count ?? 0),
                TextColumn::make('kehadiran')
                    ->label('S / I / A')
                    ->state(fn (Siswa $record): string => ($rapor = $this->raporDari($record)) === null ? '-' : "{$rapor->sakit} / {$rapor->izin} / {$rapor->tanpa_keterangan}"),
                IconColumn::make('catatan_terisi')
                    ->label('Catatan')
                    ->boolean()
                    ->state(fn (Siswa $record): bool => filled($this->raporDari($record)?->catatan)),
            ])
            ->recordActions([
                $this->aksiIsiRapor(),
                Action::make('cetak')
                    ->label('Cetak')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('gray')
                    ->visible(fn (Siswa $record): bool => ($rapor = $this->raporDari($record)) !== null && ! $rapor->trashed())
                    ->url(fn (Siswa $record): string => route('rapor.cetak', $this->raporDari($record)))
                    ->openUrlInNewTab(),
            ]);
    }

    protected function aksiIsiRapor(): Action
    {
        return Action::make('isiRapor')
            ->label('Isi Rapor')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->button()
            ->visible(fn (Siswa $record): bool => ! ($this->raporDari($record)?->trashed() ?? false))
            ->modalHeading(fn (Siswa $record): string => "Rapor {$record->nama_lengkap}")
            ->modalDescription(fn (): string => $this->semesterTerpilih()->getLabel())
            ->modalWidth('4xl')
            ->modalSubmitActionLabel('Simpan Rapor')
            ->fillForm(fn (Siswa $record): array => $this->isianRapor($record))
            ->schema(fn (): array => $this->fieldRapor())
            ->action(function (array $data, Siswa $record): void {
                $this->simpanRapor($record, $data);

                Notification::make()->success()->title('Rapor tersimpan')->send();
            });
    }

    /**
     * @return array<int, mixed>
     */
    protected function fieldRapor(): array
    {
        $skema = TahunAjaran::skemaUntuk($this->tahunAjaran()?->id);
        $genap = $this->semesterTerpilih() === Semester::Genap;

        return [
            Hidden::make('siswa_id'),
            Hidden::make('tahun_ajaran_id'),
            Hidden::make('kelas_id'),
            Hidden::make('semester'),
            Grid::make(['default' => 1, 'lg' => 2])->schema([
                Section::make('Ketidakhadiran')
                    ->columns(3)
                    ->schema([
                        TextInput::make('sakit')->numeric()->integer()->minValue(0)->suffix('hari')->required(),
                        TextInput::make('izin')->numeric()->integer()->minValue(0)->suffix('hari')->required(),
                        TextInput::make('tanpa_keterangan')->label('Alpa')->numeric()->integer()->minValue(0)->suffix('hari')->required(),
                    ]),
                Section::make('Akhlak & Kepribadian')
                    ->visible($skema->pakaiAkhlakKepribadian())
                    ->columns(2)
                    ->schema([
                        Select::make('nilai_akhlak')->label('Akhlak')->options(RaporForm::PREDIKAT_HURUF),
                        Select::make('nilai_kepribadian')->label('Kepribadian')->options(RaporForm::PREDIKAT_HURUF),
                    ]),
            ]),
            Section::make('Ekstrakurikuler')
                ->schema([
                    Repeater::make('nilaiEkstrakurikuler')
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make('Ekstrakurikuler'),
                            TableColumn::make('Nilai'),
                            TableColumn::make('Keterangan'),
                        ])
                        ->schema([
                            Select::make('ekstrakurikuler_id')
                                ->options(fn (): array => Ekstrakurikuler::query()->aktif()->orderBy('nama')->pluck('nama', 'id')->all())
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->required(),
                            Select::make('nilai')
                                ->options(RaporForm::pilihanNilaiEkstrakurikuler($skema)),
                            TextInput::make('keterangan'),
                        ])
                        ->addActionLabel('Tambah ekstrakurikuler'),
                ]),
            RaporForm::sectionPrestasi(),
            Section::make('Keputusan Kenaikan Kelas')
                ->visible($genap)
                ->columns(2)
                ->schema([
                    Select::make('keputusan')->options(KeputusanKenaikan::class),
                    DatePicker::make('tanggal_keputusan')->label('Tanggal'),
                ]),
            Textarea::make('catatan')
                ->label('Catatan Wali Kelas')
                ->rows(4)
                ->hintAction(RaporForm::aksiBuatCatatan(fn (Get $get): array => $this->barisNilaiMapel($get->integer('siswa_id'))))
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function isianRapor(Siswa $siswa): array
    {
        $rapor = $this->raporDari($siswa)?->load('nilaiEkstrakurikuler');

        return [
            'siswa_id' => $siswa->id,
            'tahun_ajaran_id' => $this->tahunAjaran()?->id,
            'kelas_id' => $this->kelasTerpilih()?->id,
            'semester' => $this->semesterTerpilih()->value,
            'sakit' => $rapor->sakit ?? 0,
            'izin' => $rapor->izin ?? 0,
            'tanpa_keterangan' => $rapor->tanpa_keterangan ?? 0,
            'nilai_akhlak' => $rapor?->nilai_akhlak,
            'nilai_kepribadian' => $rapor?->nilai_kepribadian,
            'nilaiEkstrakurikuler' => $rapor === null
                ? Ekstrakurikuler::query()->aktif()->where('is_wajib', true)->pluck('id')->map(fn (int $id): array => ['ekstrakurikuler_id' => $id])->all()
                : $rapor->nilaiEkstrakurikuler->map(fn ($nilai): array => $nilai->only(['ekstrakurikuler_id', 'nilai', 'keterangan']))->all(),
            'prestasi' => $rapor->prestasi ?? [],
            'keputusan' => $rapor?->keputusan,
            'tanggal_keputusan' => $rapor?->tanggal_keputusan,
            'catatan' => $rapor?->catatan,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function simpanRapor(Siswa $siswa, array $data): void
    {
        $tahunAjaran = $this->tahunAjaran();
        $kelas = $this->kelasTerpilih();

        abort_if($tahunAjaran === null || $kelas === null, 403);
        abort_unless(Siswa::query()->anggotaKelas($kelas->id, $tahunAjaran->id)->whereKey($siswa->id)->exists(), 403);

        DB::transaction(function () use ($siswa, $data, $tahunAjaran, $kelas): void {
            $rapor = Rapor::query()->withTrashed()->firstOrNew([
                'siswa_id' => $siswa->id,
                'tahun_ajaran_id' => $tahunAjaran->id,
                'semester' => $this->semesterTerpilih(),
            ], ['kelas_id' => $kelas->id]);

            abort_if($rapor->trashed(), 403);

            // Sections the curriculum or semester hides send nothing, and keep what is stored.
            $rapor->fill([
                'sakit' => (int) $data['sakit'],
                'izin' => (int) $data['izin'],
                'tanpa_keterangan' => (int) $data['tanpa_keterangan'],
                'prestasi' => $data['prestasi'] ?? [],
                'catatan' => $data['catatan'] ?? null,
                ...array_intersect_key($data, array_flip(['nilai_akhlak', 'nilai_kepribadian', 'keputusan', 'tanggal_keputusan'])),
            ])->save();

            $rapor->nilaiEkstrakurikuler()->delete();

            foreach (array_filter((array) ($data['nilaiEkstrakurikuler'] ?? []), is_array(...)) as $baris) {
                $rapor->nilaiEkstrakurikuler()->create([
                    'ekstrakurikuler_id' => $baris['ekstrakurikuler_id'],
                    'nilai' => $baris['nilai'] ?? null,
                    'keterangan' => $baris['keterangan'] ?? null,
                ]);
            }
        });
    }

    /**
     * The student's stored subject grades, in the shape of the report-card form, for drafting the homeroom note.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function barisNilaiMapel(int $siswaId): array
    {
        $rapor = Rapor::query()
            ->where('siswa_id', $siswaId)
            ->where('tahun_ajaran_id', $this->tahunAjaran()?->id)
            ->where('semester', $this->semesterTerpilih())
            ->first();

        return $rapor?->nilaiRapor()->get()->map(fn ($nilai): array => $nilai->only([
            'mata_pelajaran_id', 'kkm', 'nilai_pengetahuan', 'nilai_keterampilan', 'nilai_akhir', 'sikap',
        ]))->values()->all() ?? [];
    }

    protected function raporDari(Siswa $siswa): ?Rapor
    {
        return $siswa->rapor->first();
    }

    protected function tahunAjaran(): ?TahunAjaran
    {
        return once(fn (): ?TahunAjaran => TahunAjaran::query()->where('is_aktif', true)->first());
    }

    /**
     * The class chosen in the filter, if the teacher looks after it, else the first class they look after.
     */
    protected function kelasTerpilih(): ?Kelas
    {
        $perwalian = static::gtk()?->kelasPerwalian()->orderBy('tingkat')->orderBy('nama')->get();
        $dipilih = (int) ($this->tableFilters['kelas']['value'] ?? 0);

        return $perwalian?->firstWhere('id', $dipilih) ?? $perwalian?->first();
    }

    protected function semesterTerpilih(): Semester
    {
        return Semester::tryFrom((int) ($this->tableFilters['semester']['value'] ?? 0)) ?? Semester::berdasarkanTanggal(now());
    }
}
