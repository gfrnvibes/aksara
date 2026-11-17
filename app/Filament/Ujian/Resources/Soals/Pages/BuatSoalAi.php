<?php

namespace App\Filament\Ujian\Resources\Soals\Pages;

use App\Enums\JenisSoal;
use App\Enums\LevelKognitif;
use App\Enums\Semester;
use App\Filament\Resources\Kelas\KelasResource;
use App\Filament\Ujian\Resources\Soals\SoalResource;
use App\Filament\Ujian\Widgets\ProgressSoalAiWidget;
use App\Jobs\BuatSoalAi as JobBuatSoalAi;
use App\Models\User;
use App\Services\Cbt\PenyusunSoalAi;
use App\Services\Cbt\SumberMateriPerangkatAjar;
use App\Services\Gemini\KlienGemini;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Drafts many questions at once from a blueprint table, one Gemini request per row, in the background. The
 * teacher is told through the bell when the drafts are ready.
 */
class BuatSoalAi extends Page
{
    protected static string $resource = SoalResource::class;

    protected string $view = 'filament.ujian.buat-soal-ai';

    /** Rows and questions per submission, so one teacher does not spend the day's free Gemini quota. */
    public const int BARIS_MAKS = 25;

    public const int MATERI_MODAL_MAKS = 10;

    public const int SOAL_MAKS = 50;

    public int $mapel;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(array $parameters = []): bool
    {
        return KlienGemini::aktif() && SoalResource::canCreate();
    }

    public function mount(int $mapel = 0): void
    {
        abort_unless(array_key_exists($mapel, SoalResource::pilihanMapel()), 403);

        $this->mapel = $mapel;
        $this->getSchema('form')?->fill([
            'tingkat' => request()->integer('tingkat') ?: null,
            'baris' => [['jenis' => JenisSoal::PilihanGanda->value, 'jumlah' => 5]],
        ]);
    }

    public function getTitle(): string
    {
        return 'Buat Soal dengan AI · '.SoalResource::labelKelompok($this->mapel);
    }

    public function getBreadcrumbs(): array
    {
        return [SoalResource::getUrl() => 'Bank Soal', SoalResource::urlKelompok($this->mapel) => SoalResource::labelKelompok($this->mapel), 'Buat dengan AI'];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ambilPerangkatAjar')
                ->label('Ambil dari Perangkat Ajar')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->modalHeading('Sinkronkan Materi dari Perangkat Ajar')
                ->modalDescription('Pilih materi pokok & tujuan pembelajaran dari PROSEM, PROTA, atau ATP untuk mengisi tabel soal secara otomatis.')
                ->modalSubmitActionLabel('Muat ke Tabel')
                ->modalWidth('3xl')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            Select::make('tingkat')
                                ->label('Kelas')
                                ->options(KelasResource::getTingkatOptions())
                                ->default(fn (): ?int => filled($this->data['tingkat'] ?? null) ? (int) $this->data['tingkat'] : null)
                                ->live()
                                ->afterStateUpdated(fn (Set $set, Get $get) => $this->pilihDokumenPertama($set, $get))
                                ->required(),
                            Select::make('sumber')
                                ->label('Sumber')
                                ->options(SumberMateriPerangkatAjar::pilihanSumber())
                                ->default(SumberMateriPerangkatAjar::SUMBER_PERANGKAT_AJAR)
                                ->selectablePlaceholder(false)
                                ->live()
                                ->afterStateUpdated(fn (Set $set, Get $get) => $this->pilihDokumenPertama($set, $get))
                                ->required(),
                            Select::make('semester')
                                ->label('Semester')
                                ->options(Semester::class)
                                ->default(Semester::berdasarkanTanggal(now())->value)
                                ->visible(fn (Get $get): bool => in_array($get('sumber'), [
                                    SumberMateriPerangkatAjar::SUMBER_PERANGKAT_AJAR,
                                    SumberMateriPerangkatAjar::SUMBER_PROSEM,
                                    SumberMateriPerangkatAjar::SUMBER_PROTA,
                                ], true))
                                ->required(fn (Get $get): bool => $get('sumber') === SumberMateriPerangkatAjar::SUMBER_PROSEM)
                                ->live()
                                ->afterStateUpdated(fn (Set $set, Get $get) => $this->pilihDokumenPertama($set, $get)),
                        ]),
                    Select::make('dokumen_id')
                        ->label('Dokumen Perangkat Ajar')
                        ->options(fn (Get $get): array => $this->opsiDokumenPerangkat($get))
                        ->default(fn (Get $get): ?int => array_key_first($this->opsiDokumenPerangkat($get)))
                        ->placeholder('Pilih dokumen perangkat ajar')
                        ->helperText(fn (Get $get): ?string => filled($get('tingkat')) && $this->opsiDokumenPerangkat($get) === []
                            ? 'Belum ada dokumen untuk mapel & kelas ini pada tahun ajaran aktif.'
                            : null)
                        ->live()
                        ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                            if (blank($state)) {
                                $set('item_ids', []);

                                return;
                            }

                            $items = $this->materiPerangkat($get);
                            $set('item_ids', array_slice(array_keys($items), 0, static::MATERI_MODAL_MAKS));
                        })
                        ->required(),
                    CheckboxList::make('item_ids')
                        ->label('Pilih Materi (maks. '.static::MATERI_MODAL_MAKS.' materi)')
                        ->options(fn (Get $get): array => array_map(fn (array $row): string => $row['materi'], $this->materiPerangkat($get)))
                        ->descriptions(fn (Get $get): array => array_map(function (array $row): string {
                            if ($row['indikator'] === '') {
                                return 'Tanpa tujuan pembelajaran';
                            }

                            $saran = LevelKognitif::dariTeks($row['indikator']);
                            $prefix = $saran !== null ? "[{$saran->value}] " : '';

                            return $prefix.Str::limit($row['indikator'], 140);
                        }, $this->materiPerangkat($get)))
                        ->default(fn (Get $get): array => array_slice(array_keys($this->materiPerangkat($get)), 0, static::MATERI_MODAL_MAKS))
                        ->bulkToggleable()
                        ->visible(fn (Get $get): bool => filled($get('dokumen_id')))
                        ->rules([
                            fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                $dipilih = count((array) $value);
                                if ($dipilih > static::MATERI_MODAL_MAKS) {
                                    $fail('Pilih paling banyak '.static::MATERI_MODAL_MAKS.' materi sekaligus.');
                                }

                                if (($get('mode_jenis') ?? 'tunggal') === 'tunggal') {
                                    $jumlah = max(1, (int) ($get('jumlah') ?? 1));
                                    if ($dipilih * $jumlah > static::SOAL_MAKS) {
                                        $fail('Total soal ('.$dipilih.' materi × '.$jumlah.' soal) melebihi batas '.static::SOAL_MAKS.' soal sekali kirim.');
                                    }
                                }
                            },
                        ])
                        ->required(),
                    Grid::make(2)
                        ->schema([
                            Select::make('mode_jenis')
                                ->label('Mode Jenis Soal')
                                ->options([
                                    'tunggal' => 'Satu Jenis untuk Semua Materi',
                                    'komposisi' => 'Komposisi Campuran (Dibagi Merata)',
                                ])
                                ->default('tunggal')
                                ->selectablePlaceholder(false)
                                ->live()
                                ->required(),
                            Select::make('level_kognitif')
                                ->label('Level Kognitif')
                                ->options($this->opsiLevelModal(...))
                                ->default('otomatis')
                                ->placeholder('Campuran (dipilih AI)'),
                        ]),
                    Grid::make(2)
                        ->visible(fn (Get $get): bool => ($get('mode_jenis') ?? 'tunggal') === 'tunggal')
                        ->schema([
                            Select::make('jenis')
                                ->label('Jenis Soal')
                                ->options(JenisSoal::class)
                                ->default(JenisSoal::PilihanGanda->value)
                                ->selectablePlaceholder(false)
                                ->required(fn (Get $get): bool => ($get('mode_jenis') ?? 'tunggal') === 'tunggal'),
                            TextInput::make('jumlah')
                                ->label('Soal per Materi')
                                ->numeric()
                                ->integer()
                                ->minValue(1)
                                ->maxValue(PenyusunSoalAi::JUMLAH_MAKS)
                                ->default(5)
                                ->required(fn (Get $get): bool => ($get('mode_jenis') ?? 'tunggal') === 'tunggal'),
                        ]),
                    Section::make('Target Jumlah per Jenis Soal')
                        ->compact()
                        ->visible(fn (Get $get): bool => $get('mode_jenis') === 'komposisi')
                        ->description(function (Get $get): string {
                            $total = $this->totalKomposisi($get);

                            return "Total: {$total} / ".static::SOAL_MAKS.' soal · otomatis dibagi merata ke materi yang dipilih.';
                        })
                        ->columns(['default' => 2, 'md' => 5])
                        ->schema([
                            TextInput::make('komposisi_pg')
                                ->label('Pilihan Ganda')
                                ->numeric()
                                ->integer()
                                ->minValue(0)
                                ->maxValue(static::SOAL_MAKS)
                                ->default(15)
                                ->live(onBlur: true),
                            TextInput::make('komposisi_pg_kompleks')
                                ->label('PG Kompleks')
                                ->numeric()
                                ->integer()
                                ->minValue(0)
                                ->maxValue(static::SOAL_MAKS)
                                ->default(5)
                                ->live(onBlur: true),
                            TextInput::make('komposisi_benar_salah')
                                ->label('Benar/Salah')
                                ->numeric()
                                ->integer()
                                ->minValue(0)
                                ->maxValue(static::SOAL_MAKS)
                                ->default(5)
                                ->live(onBlur: true),
                            TextInput::make('komposisi_menjodohkan')
                                ->label('Menjodohkan')
                                ->numeric()
                                ->integer()
                                ->minValue(0)
                                ->maxValue(static::SOAL_MAKS)
                                ->default(0)
                                ->live(onBlur: true),
                            TextInput::make('komposisi_esai')
                                ->label('Esai')
                                ->numeric()
                                ->integer()
                                ->minValue(0)
                                ->maxValue(static::SOAL_MAKS)
                                ->default(5)
                                ->live(onBlur: true)
                                ->rules([
                                    fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                        $total = $this->totalKomposisi($get);
                                        if ($total < 1) {
                                            $fail('Isi minimal 1 soal pada salah satu jenis.');
                                        } elseif ($total > static::SOAL_MAKS) {
                                            $fail('Total komposisi ('.$total.' soal) melebihi batas '.static::SOAL_MAKS.' soal sekali kirim.');
                                        }
                                    },
                                ]),
                        ]),
                ])
                ->action(function (array $data): void {
                    $sumber = (string) $data['sumber'];
                    $dokumenId = (int) $data['dokumen_id'];
                    $semester = $data['semester'] ?? null;
                    $semesterInt = $semester instanceof Semester ? $semester->value : (filled($semester) ? (int) $semester : null);

                    $semuaMateri = SumberMateriPerangkatAjar::daftarMateri($sumber, $dokumenId, $semesterInt);
                    $materiTerpilih = [];

                    foreach ((array) $data['item_ids'] as $itemId) {
                        $item = $semuaMateri[(int) $itemId] ?? null;
                        if ($item !== null) {
                            $materiTerpilih[] = $item;
                        }
                    }

                    if ($materiTerpilih === []) {
                        Notification::make()->warning()->title('Tidak ada materi yang dimuat')->send();

                        return;
                    }

                    $pilihanLevel = $data['level_kognitif'] ?? null;
                    $baris = ($data['mode_jenis'] ?? 'tunggal') === 'komposisi'
                        ? $this->bangunBarisKomposisi($materiTerpilih, $data, $pilihanLevel)
                        : $this->bangunBarisTunggal($materiTerpilih, $data, $pilihanLevel);

                    if ($baris === []) {
                        Notification::make()->warning()->title('Tidak ada baris soal yang dihasilkan')->send();

                        return;
                    }

                    if (count($baris) > static::BARIS_MAKS) {
                        $baris = array_slice($baris, 0, static::BARIS_MAKS);
                    }

                    $this->data['tingkat'] = (int) $data['tingkat'];
                    $this->data['baris'] = $baris;
                    $this->getSchema('form')?->fill($this->data);

                    $totalSoal = array_sum(array_column($baris, 'jumlah'));

                    Notification::make()
                        ->success()
                        ->title(count($baris).' baris ('.$totalSoal.' soal) dimuat dari Perangkat Ajar')
                        ->body('Silakan periksa atau sesuaikan jenis, level, dan jumlah soal sebelum menekan Buat Draf.')
                        ->send();
                }),
        ];
    }

    private function pilihDokumenPertama(Set $set, Get $get): void
    {
        $dokumenId = array_key_first($this->opsiDokumenPerangkat($get));
        $set('dokumen_id', $dokumenId);

        if ($dokumenId === null) {
            $set('item_ids', []);

            return;
        }

        $sumber = (string) $get('sumber');
        $semester = $get('semester');
        $semesterInt = $semester instanceof Semester ? $semester->value : (filled($semester) ? (int) $semester : null);
        $items = SumberMateriPerangkatAjar::daftarMateri($sumber, $dokumenId, $semesterInt);
        $set('item_ids', array_slice(array_keys($items), 0, static::MATERI_MODAL_MAKS));
    }

    /**
     * @return array<string, string>
     */
    private function opsiLevelModal(): array
    {
        $opsi = ['otomatis' => 'Otomatis (kata kerja TP)'];

        foreach (LevelKognitif::cases() as $level) {
            $opsi[$level->value] = $level->getLabel();
        }

        return $opsi;
    }

    private function totalKomposisi(Get $get): int
    {
        return (int) ($get('komposisi_pg') ?? 0)
            + (int) ($get('komposisi_pg_kompleks') ?? 0)
            + (int) ($get('komposisi_benar_salah') ?? 0)
            + (int) ($get('komposisi_menjodohkan') ?? 0)
            + (int) ($get('komposisi_esai') ?? 0);
    }

    private function resolveLevel(mixed $pilihanLevel, string $indikator): ?string
    {
        if ($pilihanLevel === 'otomatis') {
            return LevelKognitif::dariTeks($indikator)?->value;
        }

        if ($pilihanLevel instanceof LevelKognitif) {
            return $pilihanLevel->value;
        }

        return filled($pilihanLevel) ? (string) $pilihanLevel : null;
    }

    /**
     * @param  array<int, array{id: int, materi: string, indikator: string}>  $materiTerpilih
     * @param  array<string, mixed>  $data
     * @return array<int, array{materi: string, indikator: string, jenis: string, level_kognitif: ?string, jumlah: int}>
     */
    private function bangunBarisTunggal(array $materiTerpilih, array $data, mixed $pilihanLevel): array
    {
        $jenis = $data['jenis'] instanceof JenisSoal ? $data['jenis']->value : (string) $data['jenis'];
        $jumlah = (int) $data['jumlah'];
        $baris = [];

        foreach ($materiTerpilih as $item) {
            $baris[] = [
                'materi' => $item['materi'],
                'indikator' => $item['indikator'],
                'jenis' => $jenis,
                'level_kognitif' => $this->resolveLevel($pilihanLevel, $item['indikator']),
                'jumlah' => $jumlah,
            ];
        }

        return $baris;
    }

    /**
     * Distributes target question counts per type evenly across selected topics, rotating remainders
     * and splitting quotas above PenyusunSoalAi::JUMLAH_MAKS into compliant chunks.
     *
     * @param  array<int, array{id: int, materi: string, indikator: string}>  $materiTerpilih
     * @param  array<string, mixed>  $data
     * @return array<int, array{materi: string, indikator: string, jenis: string, level_kognitif: ?string, jumlah: int}>
     */
    private function bangunBarisKomposisi(array $materiTerpilih, array $data, mixed $pilihanLevel): array
    {
        $targetPerJenis = [
            JenisSoal::PilihanGanda->value => max(0, (int) ($data['komposisi_pg'] ?? 0)),
            JenisSoal::PgKompleks->value => max(0, (int) ($data['komposisi_pg_kompleks'] ?? 0)),
            JenisSoal::BenarSalah->value => max(0, (int) ($data['komposisi_benar_salah'] ?? 0)),
            JenisSoal::Menjodohkan->value => max(0, (int) ($data['komposisi_menjodohkan'] ?? 0)),
            JenisSoal::Esai->value => max(0, (int) ($data['komposisi_esai'] ?? 0)),
        ];

        $jumlahMateri = count($materiTerpilih);
        $baris = [];
        $offset = 0;

        foreach ($targetPerJenis as $jenis => $target) {
            if ($target <= 0) {
                continue;
            }

            $dasar = intdiv($target, $jumlahMateri);
            $sisa = $target % $jumlahMateri;

            foreach ($materiTerpilih as $index => $item) {
                $dapatSisa = (($index - $offset + $jumlahMateri) % $jumlahMateri) < $sisa ? 1 : 0;
                $kuota = $dasar + $dapatSisa;

                while ($kuota > 0) {
                    $ambil = min(PenyusunSoalAi::JUMLAH_MAKS, $kuota);
                    $baris[] = [
                        'materi' => $item['materi'],
                        'indikator' => $item['indikator'],
                        'jenis' => $jenis,
                        'level_kognitif' => $this->resolveLevel($pilihanLevel, $item['indikator']),
                        'jumlah' => $ambil,
                    ];
                    $kuota -= $ambil;
                }
            }

            $offset = ($offset + $sisa) % $jumlahMateri;
        }

        return $baris;
    }

    /**
     * @return array<int, string>
     */
    private function opsiDokumenPerangkat(Get $get): array
    {
        $tingkat = $get('tingkat');
        $sumber = $get('sumber');

        if (blank($tingkat) || blank($sumber)) {
            return [];
        }

        $semester = $get('semester');
        $semesterInt = $semester instanceof Semester ? $semester->value : (filled($semester) ? (int) $semester : null);
        $user = Filament::auth()->user();
        $gtkId = $user instanceof User ? $user->gtk?->id : null;

        return SumberMateriPerangkatAjar::pilihanDokumen(
            (string) $sumber,
            $this->mapel,
            (int) $tingkat,
            $semesterInt,
            $gtkId,
        );
    }

    /**
     * @return array<int, array{id: int, materi: string, indikator: string}>
     */
    private function materiPerangkat(Get $get): array
    {
        $sumber = $get('sumber');
        $dokumenId = $get('dokumen_id');

        if (blank($sumber) || blank($dokumenId)) {
            return [];
        }

        $semester = $get('semester');
        $semesterInt = $semester instanceof Semester ? $semester->value : (filled($semester) ? (int) $semester : null);

        return SumberMateriPerangkatAjar::daftarMateri((string) $sumber, (int) $dokumenId, $semesterInt);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make()
                    ->compact()
                    ->columns(['default' => 1, 'md' => 4])
                    ->description('Satu baris untuk satu materi. Gemini hanya menerima mapel, kelas, dan isi tabel ini; setiap baris dikirim terpisah dan diproses di latar belakang. Hasilnya masuk sebagai draf dan Anda diberi tahu lewat lonceng notifikasi.')
                    ->schema([
                        Select::make('tingkat')
                            ->label('Kelas')
                            ->options(KelasResource::getTingkatOptions())
                            ->required()
                            ->columnSpan(1),
                        Repeater::make('baris')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->table([
                                TableColumn::make('Materi')->markAsRequired(),
                                TableColumn::make('Indikator (opsional)'),
                                TableColumn::make('Jenis')->width('13rem')->markAsRequired(),
                                TableColumn::make('Level')->width('12rem'),
                                TableColumn::make('Jumlah')->width('6rem')->markAsRequired(),
                            ])
                            ->schema([
                                TextInput::make('materi')->required()->maxLength(255),
                                Textarea::make('indikator')->rows(1)->autosize()->maxLength(1000),
                                Select::make('jenis')->options(JenisSoal::class)->selectablePlaceholder(false)->required(),
                                Select::make('level_kognitif')->options(LevelKognitif::class)->placeholder('Campuran'),
                                TextInput::make('jumlah')->numeric()->integer()->minValue(1)->maxValue(PenyusunSoalAi::JUMLAH_MAKS)->required(),
                            ])
                            ->compact()
                            ->minItems(1)
                            ->maxItems(static::BARIS_MAKS)
                            ->cloneable()
                            ->addActionLabel('Tambah baris')
                            ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (array_sum(array_map(fn (mixed $b): int => (int) (is_array($b) ? ($b['jumlah'] ?? 0) : 0), (array) $value)) > static::SOAL_MAKS) {
                                    $fail('Paling banyak '.static::SOAL_MAKS.' soal sekali kirim.');
                                }
                            }])
                            ->helperText(fn (Get $get): string => 'Total: '.array_sum(array_map(fn (mixed $b): int => (int) (is_array($b) ? ($b['jumlah'] ?? 0) : 0), (array) $get('baris'))).' soal (maks. '.static::SOAL_MAKS.')')
                            ->live(onBlur: true),
                    ]),
            ]);
    }

    public function kirim(): void
    {
        $data = $this->getSchema('form')?->getState() ?? [];
        $user = Filament::auth()->user();
        abort_unless($user instanceof User && array_key_exists($this->mapel, SoalResource::pilihanMapel()), 403);

        $tingkat = (int) $data['tingkat'];
        $jobs = array_map(fn (array $baris): JobBuatSoalAi => new JobBuatSoalAi($user->id, $this->mapel, $tingkat, [
            'materi' => trim($baris['materi']),
            'indikator' => filled($baris['indikator'] ?? null) ? trim($baris['indikator']) : null,
            'jenis' => $baris['jenis'] instanceof JenisSoal ? $baris['jenis']->value : (string) $baris['jenis'],
            'level_kognitif' => $baris['level_kognitif'] instanceof LevelKognitif ? $baris['level_kognitif']->value : ($baris['level_kognitif'] ?? null),
            'jumlah' => (int) $baris['jumlah'],
        ]), array_values($data['baris']));

        $url = SoalResource::urlKelompok($this->mapel, $tingkat);
        $label = SoalResource::labelKelompok($this->mapel, $tingkat);
        $userId = $user->id;

        try {
            $batch = Bus::batch($jobs)
                ->name("Soal AI {$label}")
                ->allowFailures()
                ->finally(function (Batch $batch) use ($userId, $url, $label): void {
                    $jumlah = (int) Cache::get(JobBuatSoalAi::kunciJumlah($batch->id), 0);
                    $gagal = (array) Cache::get(JobBuatSoalAi::kunciGagal($batch->id), []);
                    $penerima = User::query()->find($userId);

                    if ($penerima === null) {
                        return;
                    }

                    // A row stopped by the worker (e.g. a timeout) fails without naming itself; count those too.
                    $tanpaNama = max(0, $batch->failedJobs - count($gagal));
                    $pesanGagal = implode(', ', [...$gagal, ...($tanpaNama > 0 ? ["{$tanpaNama} baris lain"] : [])]);

                    Notification::make()
                        ->title("{$jumlah} draf soal dibuat untuk {$label}")
                        ->body($pesanGagal === '' ? 'Periksa setiap soal dan kuncinya, lalu tandai siap dipakai.' : "Gagal dibuat: {$pesanGagal}. Coba kirim ulang baris tersebut.")
                        ->status($pesanGagal === '' ? 'success' : 'warning')
                        ->actions([Action::make('lihat')->label('Lihat soal')->url($url)->markAsRead()])
                        ->sendToDatabase($penerima);
                })
                ->dispatch();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->danger()
                ->title('Gagal mengirim permintaan ke AI')
                ->body(Str::limit($exception->getMessage(), 180))
                ->persistent()
                ->send();

            return;
        }

        Cache::put(
            ProgressSoalAiWidget::kunciAktif($userId, $this->mapel),
            [
                'batch_id' => $batch->id,
                'label' => $label,
                'started_at' => now()->timestamp,
            ],
            now()->addHours(2),
        );

        Notification::make()->success()
            ->title('Permintaan dikirim ke AI')
            ->body(count($jobs).' baris sedang diproses. Anda akan diberi tahu lewat lonceng notifikasi setelah selesai.')
            ->send();

        $this->redirect($url, navigate: true);
    }
}
