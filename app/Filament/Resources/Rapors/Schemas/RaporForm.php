<?php

namespace App\Filament\Resources\Rapors\Schemas;

use App\Enums\KeputusanKenaikan;
use App\Enums\Semester;
use App\Filament\Actions\BuatCapaianKompetensiAction;
use App\Models\Ekstrakurikuler;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\NilaiEkstrakurikuler;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Services\CatatanWaliKelas\PenyusunCatatanWaliKelas;
use App\Services\CatatanWaliKelas\RingkasanRapor;
use App\Services\Kurikulum\SkemaPenilaian;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class RaporForm
{
    /**
     * @var array<string, string>
     */
    public const array PREDIKAT_SIKAP = [
        'SB' => 'SB - Sangat Baik',
        'B' => 'B - Baik',
        'C' => 'C - Cukup',
        'K' => 'K - Kurang',
    ];

    /**
     * @var array<string, string>
     */
    public const array PREDIKAT_HURUF = [
        'A' => 'A - Sangat Baik',
        'B' => 'B - Baik',
        'C' => 'C - Cukup',
        'D' => 'D - Kurang',
    ];

    /**
     * @var array<string, null>
     */
    protected const array BARIS_PRESTASI_KOSONG = ['prestasi' => null, 'nilai' => null, 'keterangan' => null];

    /**
     * The KKM is read-only in the grade table so it is not changed by a stray keystroke; this action changes it
     * deliberately through a modal.
     */
    public static function aksiUbahKkm(?string $keterangan = null, string $label = 'KKM'): Action
    {
        return Action::make('ubahKkm')
            ->label("Ubah {$label}")
            ->icon(Heroicon::PencilSquare)
            ->modalHeading("Ubah {$label}")
            ->modalDescription($keterangan ?? "{$label} ini hanya berlaku untuk rapor ini. {$label} bawaan mata pelajaran diubah di menu Mata Pelajaran.")
            ->modalWidth('sm')
            ->modalSubmitActionLabel('Simpan')
            ->fillForm(fn (TextInput $component): array => ['kkm' => $component->getState()])
            ->schema([
                TextInput::make('kkm')
                    ->label($label)
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(100)
                    ->required()
                    ->autofocus(),
            ])
            ->action(function (array $data, TextInput $component): void {
                $component->state((int) $data['kkm']);
            });
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas Rapor')
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                    ->schema([
                        Select::make('siswa_id')
                            ->label('Siswa')
                            ->relationship('siswa', 'nama_lengkap', fn (Builder $query): Builder => $query->with('kelas')->orderBy('nis'))
                            ->getOptionLabelFromRecordUsing(fn (Siswa $record): string => "{$record->nis} - {$record->nama_lengkap}".($record->kelas ? " ({$record->kelas->nama})" : ''))
                            ->searchable(['nama_lengkap', 'nis', 'nisn'])
                            ->preload()
                            ->default(fn (): ?int => request()->integer('siswa') ?: null)
                            ->live()
                            ->afterStateUpdated(function (?int $state, Get $get, Set $set): void {
                                $siswa = $state ? Siswa::query()->find($state) : null;

                                $set('kelas_id', $siswa?->kelasPadaTahunAjaran($get->integer('tahun_ajaran_id', isNullable: true)));
                            })
                            ->required(),
                        Select::make('tahun_ajaran_id')
                            ->label('Tahun Ajaran')
                            ->relationship('tahunAjaran', 'nama', fn ($query) => $query->orderByDesc('nama'))
                            ->default(fn (): ?int => TahunAjaran::query()->where('is_aktif', true)->value('id'))
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (?int $state, Get $get, Set $set): void {
                                $siswaId = $get->integer('siswa_id', isNullable: true);

                                if ($siswaId && ($siswa = Siswa::query()->find($siswaId))) {
                                    $set('kelas_id', $siswa->kelasPadaTahunAjaran($state));
                                }
                            })
                            ->required(),
                        Select::make('semester')
                            ->options(Semester::class)
                            ->default(fn (): Semester => Semester::berdasarkanTanggal(now()))
                            ->live()
                            ->required()
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                                    ->where('siswa_id', $get('siswa_id'))
                                    ->where('tahun_ajaran_id', $get('tahun_ajaran_id')),
                            )
                            ->validationMessages([
                                'unique' => 'Rapor siswa ini untuk tahun ajaran dan semester tersebut sudah ada (periksa juga filter "Data terhapus").',
                            ]),
                        Select::make('kelas_id')
                            ->label('Kelas')
                            ->relationship(
                                'kelas',
                                'nama',
                                fn (Builder $query, ?Rapor $record): Builder => Kelas::batasiYangTersedia($query, $record?->kelas_id),
                            )
                            ->default(fn (): ?int => Siswa::query()->find(request()->integer('siswa'))?->kelas_id)
                            ->preload()
                            ->required(),
                    ]),
                Section::make('Nilai Mata Pelajaran')
                    ->key('nilaiMataPelajaran')
                    ->description(fn (Get $get): string => static::skemaDari($get)->pakaiPredikat()
                        ? 'Predikat (A/B/C/D) dihitung otomatis dari nilai dan KKM saat disimpan.'
                        : 'Isi Nilai Akhir dan deskripsi capaian kompetensi tiap mata pelajaran. Format mengikuti kurikulum tahun ajaran yang dipilih.')
                    ->headerActions([BuatCapaianKompetensiAction::make('nilaiRapor', mapelPerBaris: true)])
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('nilaiRapor')
                            ->hiddenLabel()
                            ->relationship()
                            ->table(fn (Get $get): array => static::kolomTabelNilai(static::skemaDari($get)))
                            ->schema(fn (Get $get): array => static::fieldNilaiMapel(static::skemaDari($get)))
                            ->default(fn (): array => MataPelajaran::query()
                                ->aktifBerurutan()
                                ->get()
                                ->map(fn (MataPelajaran $mataPelajaran): array => [
                                    'mata_pelajaran_id' => $mataPelajaran->id,
                                    'kkm' => $mataPelajaran->kkm,
                                ])
                                ->all())
                            ->addActionLabel('Tambah mata pelajaran'),
                    ]),
                Grid::make(['default' => 1, 'lg' => 2])
                    ->columnSpanFull()
                    ->schema([
                        Section::make('Akhlak & Kepribadian')
                            ->visible(fn (Get $get): bool => static::skemaDari($get)->pakaiAkhlakKepribadian())
                            ->columns(2)
                            ->schema([
                                Select::make('nilai_akhlak')
                                    ->label('Akhlak')
                                    ->options(static::PREDIKAT_HURUF),
                                Select::make('nilai_kepribadian')
                                    ->label('Kepribadian')
                                    ->options(static::PREDIKAT_HURUF),
                            ]),
                        Section::make('Ketidakhadiran')
                            ->columnSpan(fn (Get $get): int|string => static::skemaDari($get)->pakaiAkhlakKepribadian() ? 1 : 'full')
                            ->columns(3)
                            ->schema([
                                TextInput::make('sakit')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->suffix('hari')
                                    ->required(),
                                TextInput::make('izin')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->suffix('hari')
                                    ->required(),
                                TextInput::make('tanpa_keterangan')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->suffix('hari')
                                    ->required(),
                            ]),
                    ]),
                Grid::make(['default' => 1, 'lg' => 2])
                    ->columnSpanFull()
                    ->schema([
                        static::sectionEkstrakurikuler(),
                        static::sectionPrestasi(),
                    ]),
                Section::make('Keputusan Hasil Semester I dan II')
                    ->description(fn (Get $get): ?string => static::isSemesterGenap($get('semester'))
                        ? null
                        : 'Diisi pada Semester 2 (Genap), setelah nilai semester I dan II lengkap.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('keputusan')
                            ->options(KeputusanKenaikan::class)
                            ->disabled(fn (Get $get): bool => ! static::isSemesterGenap($get('semester'))),
                        DatePicker::make('tanggal_keputusan')
                            ->label('Tanggal')
                            ->disabled(fn (Get $get): bool => ! static::isSemesterGenap($get('semester'))),
                    ]),
                Textarea::make('catatan')
                    ->label('Catatan Wali Kelas')
                    ->rows(4)
                    ->hintAction(static::aksiBuatCatatan())
                    ->columnSpanFull(),
                Section::make('Wali Kelas (Penanda Tangan)')
                    ->description('Kosongkan untuk memakai wali kelas dari data kelas saat rapor disimpan.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        TextInput::make('wali_kelas')
                            ->label('Nama Wali Kelas')
                            ->placeholder(fn (Get $get): ?string => filled($get('kelas_id')) ? Kelas::query()->whereKey($get('kelas_id'))->value('wali_kelas') : null)
                            ->maxLength(255),
                        TextInput::make('nip_wali_kelas')
                            ->label('NIP')
                            ->placeholder(fn (Get $get): ?string => filled($get('kelas_id')) && blank($get('wali_kelas')) ? Kelas::query()->whereKey($get('kelas_id'))->value('nip_wali_kelas') : null)
                            ->maxLength(30),
                    ]),
            ]);
    }

    /**
     * Drafts the homeroom note from the grades, attitude, activities and attendance currently in the form.
     */
    protected static function aksiBuatCatatan(): Action
    {
        return Action::make('buatCatatan')
            ->label('Buat catatan otomatis')
            ->icon(Heroicon::Sparkles)
            ->requiresConfirmation(fn (Get $get): bool => filled($get('catatan')))
            ->modalHeading('Ganti catatan wali kelas?')
            ->modalDescription('Catatan yang sudah ditulis akan diganti dengan catatan baru yang dibuat dari nilai di form ini.')
            ->modalSubmitActionLabel('Ganti catatan')
            ->action(function (Get $get, Set $set): void {
                $ringkasan = RingkasanRapor::dariForm([
                    'nilaiRapor' => $get('nilaiRapor'),
                    'nilaiEkstrakurikuler' => $get('nilaiEkstrakurikuler'),
                    'nilai_akhlak' => $get('nilai_akhlak'),
                    'nilai_kepribadian' => $get('nilai_kepribadian'),
                    'sakit' => $get('sakit'),
                    'izin' => $get('izin'),
                    'tanpa_keterangan' => $get('tanpa_keterangan'),
                    'semester' => $get('semester'),
                    'keputusan' => $get('keputusan'),
                    'kelas_id' => $get('kelas_id'),
                    'tahun_ajaran_id' => $get('tahun_ajaran_id'),
                ]);

                $hasil = app(PenyusunCatatanWaliKelas::class)->susun($ringkasan, RingkasanRapor::sapaanUntuk($get('siswa_id')));

                $set('catatan', $hasil['teks']);

                Notification::make()
                    ->title($hasil['sumber'] === PenyusunCatatanWaliKelas::SUMBER_GEMINI ? 'Catatan dibuat dengan Gemini' : 'Catatan dibuat dari aturan')
                    ->body($hasil['alasanGagalAi'] !== null
                        ? "Gemini tidak dapat dipakai ({$hasil['alasanGagalAi']}), jadi catatan disusun dari aturan. Coba lagi beberapa saat lagi, atau periksa dan sesuaikan catatan ini sebelum menyimpan."
                        : 'Periksa dan sesuaikan catatan sebelum menyimpan rapor.')
                    ->color($hasil['alasanGagalAi'] !== null ? 'warning' : 'success')
                    ->send();
            });
    }

    protected static function sectionEkstrakurikuler(): Section
    {
        return Section::make('Ekstrakurikuler')
            ->collapsible()
            ->schema([
                Repeater::make('nilaiEkstrakurikuler')
                    ->hiddenLabel()
                    ->relationship()
                    ->table([
                        TableColumn::make('Ekstrakurikuler'),
                        TableColumn::make('Nilai'),
                        TableColumn::make('Keterangan'),
                    ])
                    ->schema([
                        Select::make('ekstrakurikuler_id')
                            ->relationship(
                                'ekstrakurikuler',
                                'nama',
                                fn (Builder $query, ?NilaiEkstrakurikuler $record): Builder => Ekstrakurikuler::batasiYangTersedia($query, $record?->ekstrakurikuler_id),
                            )
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                            ->required(),
                        Select::make('nilai')
                            ->options(fn (Get $get): array => static::pilihanNilaiEkstrakurikuler(static::skemaDari($get, '../../tahun_ajaran_id'))),
                        TextInput::make('keterangan'),
                    ])
                    ->default(fn (): array => Ekstrakurikuler::query()
                        ->aktif()
                        ->where('is_wajib', true)
                        ->pluck('id')
                        ->map(fn (int $id): array => ['ekstrakurikuler_id' => $id])
                        ->all())
                    ->addActionLabel('Tambah ekstrakurikuler'),
            ]);
    }

    protected static function sectionPrestasi(): Section
    {
        return Section::make('Prestasi yang Pernah Diraih')
            ->collapsible()
            ->schema([
                Repeater::make('prestasi')
                    ->hiddenLabel()
                    ->table([
                        TableColumn::make('Prestasi'),
                        TableColumn::make('Nilai / Peringkat'),
                        TableColumn::make('Keterangan'),
                    ])
                    ->schema([
                        TextInput::make('prestasi')
                            ->requiredWith('nilai,keterangan')
                            ->validationMessages(['required_with' => 'Isi nama prestasinya, atau kosongkan seluruh baris.']),
                        TextInput::make('nilai'),
                        TextInput::make('keterangan'),
                    ])
                    ->defaultItems(1)
                    ->afterStateHydrated(function (Repeater $component, ?array $state): void {
                        // Show one blank row on report cards without achievements, like on a new one.
                        if (blank($state)) {
                            $component->state([(string) Str::uuid() => static::BARIS_PRESTASI_KOSONG]);
                        }
                    })
                    ->dehydrateStateUsing(fn (?array $state): array => static::buangPrestasiKosong($state))
                    ->addActionLabel('Tambah prestasi'),
            ]);
    }

    /**
     * Drop the rows left blank, so an untouched placeholder row is not saved as an achievement.
     *
     * @param  array<array-key, array<string, mixed>>|null  $baris
     * @return array<int, array<string, mixed>>
     */
    public static function buangPrestasiKosong(?array $baris): array
    {
        return array_values(array_filter(
            $baris ?? [],
            fn (array $prestasi): bool => filled($prestasi['prestasi'] ?? null)
                || filled($prestasi['nilai'] ?? null)
                || filled($prestasi['keterangan'] ?? null),
        ));
    }

    /**
     * The grading scheme of the academic year chosen in the form.
     */
    public static function skemaDari(Get $get, string $pathTahunAjaran = 'tahun_ajaran_id'): SkemaPenilaian
    {
        return TahunAjaran::skemaUntuk($get->integer($pathTahunAjaran, isNullable: true));
    }

    /**
     * @return array<int, TableColumn>
     */
    protected static function kolomTabelNilai(SkemaPenilaian $skema): array
    {
        $kolomNilai = array_map(
            fn (string $kolom): TableColumn => TableColumn::make($skema->labelKolomNilai($kolom))->width($skema->pakaiCapaianKompetensi() ? '8rem' : '13%'),
            array_keys($skema->kolomNilai()),
        );

        return [
            TableColumn::make('Mata Pelajaran')->width($skema->pakaiCapaianKompetensi() ? '24%' : '33%'),
            TableColumn::make($skema->labelKkm())->width('9rem'),
            ...$kolomNilai,
            ...($skema->pakaiSikapMapel() ? [TableColumn::make('Sikap')] : []),
            ...($skema->pakaiCapaianKompetensi() ? [TableColumn::make('Capaian Kompetensi')] : []),
        ];
    }

    /**
     * @return array<int, Component>
     */
    protected static function fieldNilaiMapel(SkemaPenilaian $skema): array
    {
        return [
            Select::make('mata_pelajaran_id')
                ->relationship('mataPelajaran', 'nama')
                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                ->live()
                ->afterStateUpdated(function (?int $state, Set $set): void {
                    $set('kkm', $state ? MataPelajaran::query()->find($state)?->kkm : null);
                })
                ->required(),
            TextInput::make('kkm')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(100)
                ->readOnly()
                ->suffixAction(static::aksiUbahKkm(label: $skema->labelKkm()))
                ->required(),
            ...array_map(
                fn (string $kolom): TextInput => TextInput::make($kolom)
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(100),
                array_keys($skema->kolomNilai()),
            ),
            ...($skema->pakaiSikapMapel() ? [
                Select::make('sikap')
                    ->options(array_combine(array_keys(static::PREDIKAT_SIKAP), array_keys(static::PREDIKAT_SIKAP))),
            ] : []),
            ...($skema->pakaiCapaianKompetensi() ? [
                Textarea::make('capaian_kompetensi')
                    ->rows(2)
                    ->autosize()
                    ->placeholder('Menunjukkan penguasaan yang baik dalam ...'),
            ] : []),
        ];
    }

    /**
     * Extracurricular grades are stored as A–D and shown the way the curriculum prints them.
     *
     * @return array<string, string>
     */
    public static function pilihanNilaiEkstrakurikuler(SkemaPenilaian $skema): array
    {
        return collect(array_keys(static::PREDIKAT_HURUF))
            ->mapWithKeys(fn (string $nilai): array => [$nilai => (string) $skema->labelNilaiEkstrakurikuler($nilai)])
            ->all();
    }

    protected static function isSemesterGenap(mixed $semester): bool
    {
        if ($semester instanceof Semester) {
            return $semester === Semester::Genap;
        }

        return (int) $semester === Semester::Genap->value;
    }
}
