<?php

namespace App\Filament\Pages;

use App\Enums\KeputusanKenaikan;
use App\Enums\Semester;
use App\Enums\StatusSiswa;
use App\Enums\TindakanKenaikan;
use App\Filament\Resources\Kelas\KelasResource;
use App\Models\Kelas;
use App\Models\Rapor;
use App\Models\RiwayatKelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Services\KenaikanKelas\ProsesKenaikanKelas;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
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
use Illuminate\Support\Str;
use UnitEnum;

/**
 * End-of-year promotion: moves the students of one class into the next academic year in one go, using the
 * decision on each student's even-semester report card as the suggested action.
 *
 * @property-read Schema $form
 */
class KenaikanKelas extends Page
{
    /**
     * The Shield permission generated for this page.
     */
    public const string IZIN = 'View:KenaikanKelas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingUp;

    protected static string|UnitEnum|null $navigationGroup = 'Buku Induk';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Kenaikan Kelas';

    protected static ?string $title = 'Kenaikan Kelas & Kelulusan';

    protected static ?string $slug = 'kenaikan-kelas';

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
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Kelas yang Diproses')
                    ->description('Pilih kelas dan tahun ajaran yang ditutup, lalu tahun ajaran baru tempat siswa dipindahkan.')
                    ->columns(['default' => 1, 'md' => 3])
                    ->schema([
                        Select::make('tahun_ajaran_asal_id')
                            ->label('Tahun Ajaran Asal')
                            ->options(fn (): array => TahunAjaran::query()->orderByDesc('nama')->pluck('nama', 'id')->all())
                            ->default(fn (): ?int => TahunAjaran::query()->where('is_aktif', true)->value('id'))
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                $set('tahun_ajaran_tujuan_id', static::tahunSesudah($get->integer('tahun_ajaran_asal_id', isNullable: true)));
                                static::muatSiswa($get, $set);
                            })
                            ->required(),
                        Select::make('kelas_asal_id')
                            ->label('Kelas Asal')
                            ->options(fn (): array => Kelas::query()->aktif()->orderBy('tingkat')->orderBy('nama')->pluck('nama', 'id')->all())
                            ->live()
                            ->afterStateUpdated(function (?int $state, Get $get, Set $set): void {
                                $set('kelas_naik_id', static::kelasNaikYangCocok($state));
                                $set('kelas_tinggal_id', $state);
                                static::muatSiswa($get, $set);
                            })
                            ->required(),
                        Select::make('tahun_ajaran_tujuan_id')
                            ->label('Tahun Ajaran Tujuan')
                            ->options(fn (Get $get): array => TahunAjaran::query()
                                ->when(
                                    static::cariTahunAjaran($get->integer('tahun_ajaran_asal_id', isNullable: true))?->nama,
                                    fn ($query, string $namaAsal) => $query->where('nama', '>', $namaAsal),
                                )
                                ->orderBy('nama')
                                ->pluck('nama', 'id')
                                ->all())
                            ->default(fn (): ?int => static::tahunSesudah(TahunAjaran::query()->where('is_aktif', true)->value('id')))
                            ->helperText('Belum ada? Tambahkan dulu di menu Tahun Ajaran.')
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => static::muatSiswa($get, $set))
                            ->required(),
                        Select::make('kelas_naik_id')
                            ->label('Kelas Tujuan (Naik Kelas)')
                            ->options(fn (Get $get): array => static::kelasSetingkat($get->integer('kelas_asal_id', isNullable: true), selisihTingkat: 1))
                            ->visible(fn (Get $get): bool => ! static::tingkatAkhir($get->integer('kelas_asal_id', isNullable: true)))
                            ->required(fn (Get $get): bool => collect($get->array('siswa'))->contains('tindakan', TindakanKenaikan::Naik->value))
                            ->validationMessages(['required' => 'Pilih kelas tujuan untuk siswa yang naik kelas.']),
                        Select::make('kelas_tinggal_id')
                            ->label('Kelas (Tinggal di Kelas)')
                            ->options(fn (Get $get): array => static::kelasSetingkat($get->integer('kelas_asal_id', isNullable: true), selisihTingkat: 0))
                            ->helperText('Biasanya kelas yang sama.')
                            ->required(fn (Get $get): bool => collect($get->array('siswa'))->contains('tindakan', TindakanKenaikan::TinggalDiKelas->value)),
                    ]),
                Section::make('Daftar Siswa')
                    ->description('Tindakan diisi dari keputusan rapor semester Genap tahun ajaran asal. Siswa yang belum punya keputusan ditandai "Lewati" dan tidak dipindahkan.')
                    ->visible(fn (Get $get): bool => filled($get->integer('kelas_asal_id', isNullable: true)))
                    ->schema([
                        Text::make('Tidak ada siswa aktif di kelas ini.')
                            ->visible(fn (Get $get): bool => blank($get('siswa'))),
                        Repeater::make('siswa')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Siswa'),
                                TableColumn::make('Keputusan Rapor')->width('25%'),
                                TableColumn::make('Tindakan')->width('25%'),
                            ])
                            ->schema([
                                Hidden::make('siswa_id'),
                                TextEntry::make('nama')->hiddenLabel(),
                                TextEntry::make('keputusan')->hiddenLabel(),
                                Select::make('tindakan')
                                    ->hiddenLabel()
                                    ->options(fn (Get $get): array => static::pilihanTindakan($get->integer('../../kelas_asal_id', isNullable: true)))
                                    ->selectablePlaceholder(false)
                                    ->required(),
                            ])
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->visible(fn (Get $get): bool => filled($get('siswa'))),
                    ]),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('prosesKenaikan')
                ->label('Proses Kenaikan Kelas')
                ->icon(Heroicon::OutlinedArrowTrendingUp)
                ->requiresConfirmation()
                ->modalHeading('Proses kenaikan kelas?')
                ->modalDescription(fn (): string => $this->ringkasanTindakan())
                ->modalSubmitActionLabel('Ya, proses')
                ->disabled(fn (): bool => blank($this->data['siswa'] ?? null))
                ->action(fn () => $this->proses()),
        ];
    }

    public function proses(): void
    {
        $data = $this->form->getState();

        $tindakanPerSiswa = collect(static::barisDari($data))
            ->mapWithKeys(fn (array $baris): array => [(int) $baris['siswa_id'] => TindakanKenaikan::from($baris['tindakan'])])
            ->all();

        try {
            $jumlah = app(ProsesKenaikanKelas::class)->proses(
                TahunAjaran::query()->findOrFail((int) $data['tahun_ajaran_asal_id']),
                TahunAjaran::query()->findOrFail((int) $data['tahun_ajaran_tujuan_id']),
                Kelas::query()->findOrFail((int) $data['kelas_asal_id']),
                static::cariKelas(filled($data['kelas_naik_id'] ?? null) ? (int) $data['kelas_naik_id'] : null),
                static::cariKelas(filled($data['kelas_tinggal_id'] ?? null) ? (int) $data['kelas_tinggal_id'] : null),
                $tindakanPerSiswa,
            );
        } catch (DomainException $exception) {
            Notification::make()->danger()->title('Kenaikan kelas tidak diproses')->body($exception->getMessage())->send();

            return;
        }

        Notification::make()
            ->success()
            ->title('Kenaikan kelas selesai diproses')
            ->body(collect($jumlah)
                ->filter()
                ->map(fn (int $total, string $tindakan): string => TindakanKenaikan::from($tindakan)->getLabel().": {$total} siswa")
                ->implode(', '))
            ->send();

        $this->data['siswa'] = static::barisSiswa((int) $data['kelas_asal_id'], (int) $data['tahun_ajaran_asal_id'], (int) $data['tahun_ajaran_tujuan_id']);
    }

    protected function ringkasanTindakan(): string
    {
        $ringkasan = collect(static::barisDari($this->data))
            ->countBy('tindakan')
            ->map(fn (int $total, string $tindakan): string => TindakanKenaikan::from($tindakan)->getLabel().": {$total}")
            ->implode(', ');

        return "{$ringkasan}. Siswa yang naik atau tinggal dipindahkan ke tahun ajaran tujuan, siswa yang lulus menjadi alumni. Kelas tahun ajaran asal tetap tersimpan di riwayat kelas.";
    }

    /**
     * The student rows of the form state.
     *
     * @param  array<string, mixed>|null  $data
     * @return array<array-key, mixed>
     */
    protected static function barisDari(?array $data): array
    {
        return is_array($data['siswa'] ?? null) ? $data['siswa'] : [];
    }

    protected static function muatSiswa(Get $get, Set $set): void
    {
        $set('siswa', static::barisSiswa($get->integer('kelas_asal_id', isNullable: true), $get->integer('tahun_ajaran_asal_id', isNullable: true), $get->integer('tahun_ajaran_tujuan_id', isNullable: true)));
    }

    /**
     * One row per active student of the class, with the action suggested by the even-semester decision.
     *
     * @return array<string, array{siswa_id: int, nama: string, keputusan: string, tindakan: string}>
     */
    protected static function barisSiswa(?int $kelasId, ?int $tahunAsalId, ?int $tahunTujuanId): array
    {
        $kelas = static::cariKelas($kelasId);

        if ($kelas === null) {
            return [];
        }

        $daftarSiswa = Siswa::query()
            ->where('kelas_id', $kelas->id)
            ->where('status', StatusSiswa::Aktif)
            ->orderBy('nama_lengkap')
            ->get();

        $keputusan = Rapor::query()
            ->whereIn('siswa_id', $daftarSiswa->modelKeys())
            ->where('tahun_ajaran_id', $tahunAsalId)
            ->where('semester', Semester::Genap)
            ->pluck('keputusan', 'siswa_id');

        $sudahDiproses = RiwayatKelas::query()
            ->whereIn('siswa_id', $daftarSiswa->modelKeys())
            ->where('tahun_ajaran_id', $tahunTujuanId)
            ->pluck('siswa_id')
            ->flip();

        $tingkatAkhir = static::tingkatAkhir($kelas->id);

        return $daftarSiswa
            ->mapWithKeys(function (Siswa $siswa) use ($keputusan, $sudahDiproses, $tingkatAkhir): array {
                /** @var KeputusanKenaikan|null $keputusanRapor */
                $keputusanRapor = $keputusan->get($siswa->id);

                $tindakan = match (TindakanKenaikan::dariKeputusan($keputusanRapor)) {
                    TindakanKenaikan::Naik, TindakanKenaikan::Lulus => $tingkatAkhir ? TindakanKenaikan::Lulus : TindakanKenaikan::Naik,
                    default => TindakanKenaikan::dariKeputusan($keputusanRapor),
                };

                $keterangan = $keputusanRapor?->getLabel() ?? 'Belum ada keputusan';

                if ($sudahDiproses->has($siswa->id)) {
                    $tindakan = TindakanKenaikan::Lewati;
                    $keterangan .= ' (sudah diproses)';
                }

                return [(string) Str::uuid() => [
                    'siswa_id' => $siswa->id,
                    'nama' => "{$siswa->nis} - {$siswa->nama_lengkap}",
                    'keputusan' => $keterangan,
                    'tindakan' => $tindakan->value,
                ]];
            })
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected static function pilihanTindakan(?int $kelasId): array
    {
        $tersedia = static::tingkatAkhir($kelasId)
            ? [TindakanKenaikan::Lulus, TindakanKenaikan::TinggalDiKelas, TindakanKenaikan::Lewati]
            : [TindakanKenaikan::Naik, TindakanKenaikan::TinggalDiKelas, TindakanKenaikan::Lewati];

        return collect($tersedia)->mapWithKeys(fn (TindakanKenaikan $tindakan): array => [$tindakan->value => $tindakan->getLabel()])->all();
    }

    protected static function tingkatAkhir(?int $kelasId): bool
    {
        $kelas = static::cariKelas($kelasId);

        return $kelas !== null && $kelas->tingkat >= max(array_keys(KelasResource::getTingkatOptions()));
    }

    /**
     * @return array<int, string>
     */
    protected static function kelasSetingkat(?int $kelasId, int $selisihTingkat): array
    {
        $kelas = static::cariKelas($kelasId);

        if ($kelas === null) {
            return [];
        }

        return Kelas::query()
            ->aktif()
            ->where('tingkat', $kelas->tingkat + $selisihTingkat)
            ->orderBy('nama')
            ->pluck('nama', 'id')
            ->all();
    }

    /**
     * The class one grade up with the same parallel letter (VII-A → VIII-A), or the only class of that grade.
     */
    protected static function kelasNaikYangCocok(?int $kelasId): ?int
    {
        $kelas = static::cariKelas($kelasId);
        $pilihan = static::kelasSetingkat($kelasId, selisihTingkat: 1);

        if ($kelas === null || $pilihan === []) {
            return null;
        }

        $paralel = Str::afterLast($kelas->nama, '-');

        foreach ($pilihan as $id => $nama) {
            if (Str::contains($kelas->nama, '-') && Str::afterLast($nama, '-') === $paralel) {
                return $id;
            }
        }

        return count($pilihan) === 1 ? array_key_first($pilihan) : null;
    }

    protected static function cariKelas(?int $kelasId): ?Kelas
    {
        return $kelasId === null ? null : Kelas::query()->find($kelasId);
    }

    protected static function cariTahunAjaran(?int $tahunAjaranId): ?TahunAjaran
    {
        return $tahunAjaranId === null ? null : TahunAjaran::query()->find($tahunAjaranId);
    }

    protected static function tahunSesudah(?int $tahunAjaranId): ?int
    {
        $tahun = static::cariTahunAjaran($tahunAjaranId);

        return $tahun
            ? TahunAjaran::query()->where('nama', '>', $tahun->nama)->orderBy('nama')->value('id')
            : null;
    }
}
