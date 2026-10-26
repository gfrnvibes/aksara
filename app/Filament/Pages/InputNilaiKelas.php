<?php

namespace App\Filament\Pages;

use App\Enums\Semester;
use App\Filament\Resources\Rapors\Schemas\RaporForm;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\NilaiRapor;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Enter one subject's grades for a whole class at once, the way teachers fill in their mark sheets. Each
 * student's report card for the semester is created when it does not exist yet.
 *
 * @property-read Schema $form
 */
class InputNilaiKelas extends Page
{
    /**
     * The Shield permission generated for this page.
     */
    public const string IZIN = 'View:InputNilaiKelas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = 'Buku Induk';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Input Nilai per Kelas';

    protected static ?string $title = 'Input Nilai per Kelas';

    protected static ?string $slug = 'input-nilai-kelas';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->can(static::IZIN) ?? false;
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedSchema::make('form'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $muatUlang = fn (Get $get, Set $set) => static::muatNilai($get, $set);

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Kelas & Mata Pelajaran')
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 5])
                    ->schema([
                        Select::make('tahun_ajaran_id')
                            ->label('Tahun Ajaran')
                            ->options(fn (): array => TahunAjaran::query()->orderByDesc('nama')->pluck('nama', 'id')->all())
                            ->default(fn (): ?int => TahunAjaran::query()->where('is_aktif', true)->value('id'))
                            ->live()
                            ->afterStateUpdated($muatUlang)
                            ->required(),
                        Select::make('semester')
                            ->options(Semester::class)
                            ->default(fn (): Semester => Semester::berdasarkanTanggal(now()))
                            ->live()
                            ->afterStateUpdated($muatUlang)
                            ->required(),
                        Select::make('kelas_id')
                            ->label('Kelas')
                            ->options(fn (): array => Kelas::query()->aktif()->orderBy('tingkat')->orderBy('nama')->pluck('nama', 'id')->all())
                            ->live()
                            ->afterStateUpdated($muatUlang)
                            ->required(),
                        Select::make('mata_pelajaran_id')
                            ->label('Mata Pelajaran')
                            ->options(fn (): array => MataPelajaran::query()->aktifBerurutan()->pluck('nama', 'id')->all())
                            ->searchable()
                            ->live()
                            ->afterStateUpdated($muatUlang)
                            ->required(),
                        TextInput::make('kkm')
                            ->label('KKM')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(100)
                            ->readOnly()
                            ->suffixAction(RaporForm::aksiUbahKkm('KKM ini dipakai untuk semua siswa kelas ini pada mata pelajaran dan semester yang dipilih. KKM bawaan mata pelajaran diubah di menu Mata Pelajaran.'))
                            ->required(),
                    ]),
                Section::make('Daftar Nilai')
                    ->description('Predikat dihitung otomatis dari nilai dan KKM. Baris yang dibiarkan kosong tidak dibuatkan rapor.')
                    ->visible(fn (Get $get): bool => $get->filled('kelas_id') && $get->filled('mata_pelajaran_id'))
                    ->schema([
                        Text::make('Belum ada siswa di kelas ini pada tahun ajaran tersebut.')
                            ->visible(fn (Get $get): bool => $get->blank('nilai')),
                        Repeater::make('nilai')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Siswa'),
                                TableColumn::make('Pengetahuan (KI-3)')->width('16%'),
                                TableColumn::make('Keterampilan (KI-4)')->width('16%'),
                                TableColumn::make('Sikap')->width('16%'),
                            ])
                            ->schema([
                                Hidden::make('siswa_id'),
                                Hidden::make('terkunci'),
                                TextEntry::make('nama')->hiddenLabel(),
                                TextInput::make('nilai_pengetahuan')
                                    ->hiddenLabel()
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->disabled(fn (Get $get): bool => (bool) $get('terkunci')),
                                TextInput::make('nilai_keterampilan')
                                    ->hiddenLabel()
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->disabled(fn (Get $get): bool => (bool) $get('terkunci')),
                                Select::make('sikap')
                                    ->hiddenLabel()
                                    ->options(array_combine(array_keys(RaporForm::PREDIKAT_SIKAP), array_keys(RaporForm::PREDIKAT_SIKAP)))
                                    ->disabled(fn (Get $get): bool => (bool) $get('terkunci')),
                            ])
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->compact()
                            ->visible(fn (Get $get): bool => $get->filled('nilai')),
                    ]),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('simpanNilai')
                ->label('Simpan Nilai')
                ->icon(Heroicon::OutlinedCheck)
                ->keyBindings(['mod+s'])
                ->disabled(fn (): bool => blank($this->data['nilai'] ?? null))
                ->action(fn () => $this->simpan()),
        ];
    }

    public function simpan(): void
    {
        $data = $this->form->getState();

        $tahunAjaranId = (int) $data['tahun_ajaran_id'];
        $semester = $data['semester'] instanceof Semester ? $data['semester'] : Semester::from((int) $data['semester']);
        $kelasId = (int) $data['kelas_id'];
        $mataPelajaranId = (int) $data['mata_pelajaran_id'];
        $kkm = (int) $data['kkm'];

        $anggota = Siswa::query()->anggotaKelas($kelasId, $tahunAjaranId)->pluck('id')->flip();
        $tersimpan = 0;

        DB::transaction(function () use ($data, $anggota, $tahunAjaranId, $semester, $kelasId, $mataPelajaranId, $kkm, &$tersimpan): void {
            foreach (static::barisDari($data) as $baris) {
                $siswaId = (int) ($baris['siswa_id'] ?? 0);
                $nilai = [
                    'nilai_pengetahuan' => filled($baris['nilai_pengetahuan'] ?? null) ? (int) $baris['nilai_pengetahuan'] : null,
                    'nilai_keterampilan' => filled($baris['nilai_keterampilan'] ?? null) ? (int) $baris['nilai_keterampilan'] : null,
                    'sikap' => filled($baris['sikap'] ?? null) ? (string) $baris['sikap'] : null,
                ];

                if (! $anggota->has($siswaId)) {
                    continue;
                }

                $rapor = Rapor::query()
                    ->withTrashed()
                    ->where('siswa_id', $siswaId)
                    ->where('tahun_ajaran_id', $tahunAjaranId)
                    ->where('semester', $semester)
                    ->first();

                if ($rapor?->trashed()) {
                    continue;
                }

                $nilaiLama = $rapor?->nilaiRapor()->where('mata_pelajaran_id', $mataPelajaranId)->first();

                if ($nilaiLama === null && array_filter($nilai, filled(...)) === []) {
                    continue;
                }

                $rapor ??= Rapor::query()->create([
                    'siswa_id' => $siswaId,
                    'tahun_ajaran_id' => $tahunAjaranId,
                    'semester' => $semester,
                    'kelas_id' => $kelasId,
                ]);

                NilaiRapor::query()->updateOrCreate(
                    ['rapor_id' => $rapor->id, 'mata_pelajaran_id' => $mataPelajaranId],
                    ['kkm' => $kkm, ...$nilai],
                );

                $tersimpan++;
            }
        });

        Notification::make()
            ->success()
            ->title('Nilai tersimpan')
            ->body("{$tersimpan} siswa diperbarui.")
            ->send();
    }

    protected static function muatNilai(Get $get, Set $set): void
    {
        $tahunAjaranId = $get->integer('tahun_ajaran_id', isNullable: true);
        $kelasId = $get->integer('kelas_id', isNullable: true);
        $mataPelajaranId = $get->integer('mata_pelajaran_id', isNullable: true);
        $semester = $get('semester');
        $semester = $semester instanceof Semester ? $semester : Semester::tryFrom((int) $semester);

        if ($tahunAjaranId === null || $kelasId === null || $mataPelajaranId === null || $semester === null) {
            $set('nilai', []);

            return;
        }

        $daftarSiswa = Siswa::query()->anggotaKelas($kelasId, $tahunAjaranId)->orderBy('nama_lengkap')->get();

        $daftarRapor = Rapor::query()
            ->withTrashed()
            ->whereIn('siswa_id', $daftarSiswa->modelKeys())
            ->where('tahun_ajaran_id', $tahunAjaranId)
            ->where('semester', $semester)
            ->with(['nilaiRapor' => fn ($query) => $query->where('mata_pelajaran_id', $mataPelajaranId)])
            ->get()
            ->keyBy('siswa_id');

        $kkmTersimpan = $daftarRapor->flatMap(fn (Rapor $rapor) => $rapor->nilaiRapor)->pluck('kkm')->first();

        $set('kkm', $kkmTersimpan ?? MataPelajaran::query()->whereKey($mataPelajaranId)->value('kkm'));

        $set('nilai', $daftarSiswa
            ->mapWithKeys(function (Siswa $siswa) use ($daftarRapor): array {
                /** @var Rapor|null $rapor */
                $rapor = $daftarRapor->get($siswa->id);
                $nilai = $rapor?->nilaiRapor->first();
                $terkunci = $rapor?->trashed() ?? false;

                return [(string) Str::uuid() => [
                    'siswa_id' => $siswa->id,
                    'terkunci' => $terkunci,
                    'nama' => "{$siswa->nis} - {$siswa->nama_lengkap}".($terkunci ? ' (rapor ada di Data terhapus)' : ''),
                    'nilai_pengetahuan' => $nilai?->nilai_pengetahuan,
                    'nilai_keterampilan' => $nilai?->nilai_keterampilan,
                    'sikap' => $nilai?->sikap,
                ]];
            })
            ->all());
    }

    /**
     * The grade rows of the form state.
     *
     * @param  array<string, mixed>  $data
     * @return array<array-key, array<string, mixed>>
     */
    protected static function barisDari(array $data): array
    {
        return is_array($data['nilai'] ?? null) ? array_filter($data['nilai'], is_array(...)) : [];
    }
}
