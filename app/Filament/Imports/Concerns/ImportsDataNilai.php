<?php

namespace App\Filament\Imports\Concerns;

use App\Enums\KeputusanKenaikan;
use App\Enums\Semester;
use App\Filament\Resources\Rapors\Schemas\RaporForm;
use App\Models\Ekstrakurikuler;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\NilaiEkstrakurikuler;
use App\Models\NilaiRapor;
use App\Models\Rapor;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Spreadsheet columns for one report card (one student in one semester), shared by the grade and combined importers.
 *
 * Each active subject gets a knowledge and a skill column, and each extracurricular gets one column.
 */
trait ImportsDataNilai
{
    /**
     * @var array<int, string>
     */
    public const array PREDIKAT = ['A', 'B', 'C', 'D'];

    /**
     * @var Collection<int, MataPelajaran>|null
     */
    protected ?Collection $daftarMataPelajaran = null;

    /**
     * @var Collection<int, Ekstrakurikuler>|null
     */
    protected ?Collection $daftarEkstrakurikuler = null;

    /**
     * @return array<int, ImportColumn>
     */
    public static function kolomNilai(bool $wajib): array
    {
        $aturanIdentitas = $wajib ? 'required' : 'nullable';

        $kolomMataPelajaran = MataPelajaran::query()->aktifBerurutan()->get()
            ->flatMap(fn (MataPelajaran $mataPelajaran): array => [
                ImportColumn::make("mapel_{$mataPelajaran->id}_pengetahuan")
                    ->label(static::labelNilaiPengetahuan($mataPelajaran))
                    ->guess(["{$mataPelajaran->nama} - Pengetahuan"])
                    ->integer()
                    ->rules(['nullable', 'integer', 'min:0', 'max:100'])
                    ->example('85'),
                ImportColumn::make("mapel_{$mataPelajaran->id}_keterampilan")
                    ->label(static::labelNilaiKeterampilan($mataPelajaran))
                    ->guess(["{$mataPelajaran->nama} - Keterampilan"])
                    ->integer()
                    ->rules(['nullable', 'integer', 'min:0', 'max:100'])
                    ->example('82'),
                ImportColumn::make("mapel_{$mataPelajaran->id}_sikap")
                    ->label(static::labelSikap($mataPelajaran))
                    ->castStateUsing(fn (mixed $state): ?string => static::predikat($state))
                    ->rules(['nullable', Rule::in(array_keys(RaporForm::PREDIKAT_SIKAP))])
                    ->example('B'),
            ])
            ->all();

        $kolomEkstrakurikuler = Ekstrakurikuler::query()->aktif()->orderBy('nama')->get()
            ->map(fn (Ekstrakurikuler $ekstrakurikuler): ImportColumn => ImportColumn::make("ekskul_{$ekstrakurikuler->id}")
                ->label(static::labelEkstrakurikuler($ekstrakurikuler))
                ->castStateUsing(fn (mixed $state): ?string => static::predikat($state))
                ->rules(['nullable', Rule::in(static::PREDIKAT)])
                ->example('B'))
            ->all();

        return [
            ImportColumn::make('tahun_ajaran')
                ->label('Tahun Ajaran')
                ->requiredMapping($wajib)
                ->castStateUsing(fn (mixed $state): ?string => filled($state) ? str_replace(' ', '', trim((string) $state)) : null)
                ->rules([$aturanIdentitas, 'regex:/^\d{4}\/\d{4}$/'])
                ->example('2025/2026'),
            ImportColumn::make('semester')
                ->label('Semester (1/2)')
                ->requiredMapping($wajib)
                ->castStateUsing(fn (mixed $state): ?int => static::semester($state))
                ->rules([$aturanIdentitas, Rule::enum(Semester::class)])
                ->example('1'),
            ImportColumn::make('kelas_rapor')
                ->label('Kelas pada Semester')
                ->castStateUsing(fn (mixed $state): ?string => filled($state) ? trim((string) $state) : null)
                ->rules(['nullable', Rule::exists(Kelas::class, 'nama')])
                ->example('VII-A'),
            ImportColumn::make('sakit')
                ->label('Sakit (hari)')
                ->integer()
                ->rules(['nullable', 'integer', 'min:0'])
                ->example('0'),
            ImportColumn::make('izin')
                ->label('Izin (hari)')
                ->integer()
                ->rules(['nullable', 'integer', 'min:0'])
                ->example('1'),
            ImportColumn::make('tanpa_keterangan')
                ->label('Tanpa Keterangan (hari)')
                ->integer()
                ->rules(['nullable', 'integer', 'min:0'])
                ->example('0'),
            ...$kolomMataPelajaran,
            ...$kolomEkstrakurikuler,
            ImportColumn::make('nilai_akhlak')
                ->label('Akhlak')
                ->castStateUsing(fn (mixed $state): ?string => static::predikat($state))
                ->rules(['nullable', Rule::in(static::PREDIKAT)])
                ->example('A'),
            ImportColumn::make('nilai_kepribadian')
                ->label('Kepribadian')
                ->castStateUsing(fn (mixed $state): ?string => static::predikat($state))
                ->rules(['nullable', Rule::in(static::PREDIKAT)])
                ->example('A'),
            ImportColumn::make('keputusan')
                ->label('Keputusan (naik/tinggal/lulus)')
                ->castStateUsing(fn (mixed $state): ?string => static::keputusan($state))
                ->rules(['nullable', Rule::enum(KeputusanKenaikan::class)]),
            ImportColumn::make('catatan')
                ->label('Catatan Wali Kelas'),
        ];
    }

    public static function labelNilaiPengetahuan(MataPelajaran $mataPelajaran): string
    {
        return "{$mataPelajaran->nama} (P)";
    }

    public static function labelNilaiKeterampilan(MataPelajaran $mataPelajaran): string
    {
        return "{$mataPelajaran->nama} (K)";
    }

    public static function labelSikap(MataPelajaran $mataPelajaran): string
    {
        return "{$mataPelajaran->nama} (Sikap)";
    }

    public static function labelEkstrakurikuler(Ekstrakurikuler $ekstrakurikuler): string
    {
        return "Ekskul {$ekstrakurikuler->nama}";
    }

    /**
     * Whether the row carries a report card at all (the combined template allows biodata-only rows).
     */
    protected function adaDataRapor(): bool
    {
        return filled($this->data['tahun_ajaran'] ?? null) || filled($this->data['semester'] ?? null);
    }

    /**
     * Find the report card this row describes, without creating anything yet.
     */
    protected function cariRapor(Siswa $siswa): Rapor
    {
        $tahunAjaranId = TahunAjaran::query()->where('nama', $this->data['tahun_ajaran'] ?? null)->value('id');

        if ($siswa->exists && $tahunAjaranId && filled($this->data['semester'] ?? null)) {
            $rapor = Rapor::withTrashed()
                ->whereBelongsTo($siswa)
                ->where('tahun_ajaran_id', $tahunAjaranId)
                ->where('semester', $this->data['semester'])
                ->first();

            if ($rapor?->trashed()) {
                throw new RowImportFailedException("Rapor {$this->data['tahun_ajaran']} semester {$this->data['semester']} untuk NIS {$siswa->nis} ada di data terhapus. Pulihkan rapor tersebut terlebih dahulu.");
            }

            if ($rapor) {
                return $rapor;
            }
        }

        return new Rapor;
    }

    /**
     * Save the report card of this row together with its subject and extracurricular grades.
     */
    protected function simpanRapor(Siswa $siswa, Rapor $rapor): Rapor
    {
        if (blank($this->data['tahun_ajaran'] ?? null) || blank($this->data['semester'] ?? null)) {
            throw new RowImportFailedException('Tahun Ajaran dan Semester wajib diisi bersamaan untuk menyimpan nilai.');
        }

        $kelasId = filled($this->data['kelas_rapor'] ?? null)
            ? Kelas::query()->where('nama', $this->data['kelas_rapor'])->value('id')
            : ($rapor->kelas_id ?? $siswa->kelas_id);

        if ($kelasId === null) {
            throw new RowImportFailedException('Kelas pada Semester wajib diisi karena siswa belum memiliki kelas.');
        }

        $rapor->fill([
            'siswa_id' => $siswa->id,
            'tahun_ajaran_id' => TahunAjaran::query()->firstOrCreate(['nama' => $this->data['tahun_ajaran']])->id,
            'semester' => $this->data['semester'],
            'kelas_id' => $kelasId,
        ]);

        foreach (['sakit', 'izin', 'tanpa_keterangan'] as $atribut) {
            if ($this->kolomTerpetakan($atribut)) {
                $rapor->setAttribute($atribut, $this->data[$atribut] ?? 0);
            }
        }

        foreach (['nilai_akhlak', 'nilai_kepribadian', 'keputusan', 'catatan'] as $atribut) {
            if ($this->kolomTerpetakan($atribut)) {
                $rapor->setAttribute($atribut, $this->data[$atribut]);
            }
        }

        $rapor->save();

        $this->simpanNilaiMataPelajaran($rapor);
        $this->simpanNilaiEkstrakurikuler($rapor);

        return $rapor;
    }

    protected function simpanNilaiMataPelajaran(Rapor $rapor): void
    {
        $this->daftarMataPelajaran ??= MataPelajaran::query()->aktifBerurutan()->get();

        foreach ($this->daftarMataPelajaran as $mataPelajaran) {
            $kolomPengetahuan = "mapel_{$mataPelajaran->id}_pengetahuan";
            $kolomKeterampilan = "mapel_{$mataPelajaran->id}_keterampilan";
            $kolomSikap = "mapel_{$mataPelajaran->id}_sikap";

            if (! $this->kolomTerpetakan($kolomPengetahuan) && ! $this->kolomTerpetakan($kolomKeterampilan) && ! $this->kolomTerpetakan($kolomSikap)) {
                continue;
            }

            $nilaiRapor = NilaiRapor::query()->firstOrNew([
                'rapor_id' => $rapor->id,
                'mata_pelajaran_id' => $mataPelajaran->id,
            ]);

            if ($this->kolomTerpetakan($kolomPengetahuan)) {
                $nilaiRapor->nilai_pengetahuan = $this->data[$kolomPengetahuan];
            }

            if ($this->kolomTerpetakan($kolomKeterampilan)) {
                $nilaiRapor->nilai_keterampilan = $this->data[$kolomKeterampilan];
            }

            if ($this->kolomTerpetakan($kolomSikap)) {
                $nilaiRapor->sikap = $this->data[$kolomSikap];
            }

            if (! $nilaiRapor->exists && $nilaiRapor->nilai_pengetahuan === null && $nilaiRapor->nilai_keterampilan === null && $nilaiRapor->sikap === null) {
                continue;
            }

            $nilaiRapor->kkm ??= $mataPelajaran->kkm;
            $nilaiRapor->save();
        }
    }

    protected function simpanNilaiEkstrakurikuler(Rapor $rapor): void
    {
        $this->daftarEkstrakurikuler ??= Ekstrakurikuler::query()->get();

        foreach ($this->daftarEkstrakurikuler as $ekstrakurikuler) {
            $kolom = "ekskul_{$ekstrakurikuler->id}";

            if (! $this->kolomTerpetakan($kolom)) {
                continue;
            }

            $nilai = $this->data[$kolom];
            $kriteria = ['rapor_id' => $rapor->id, 'ekstrakurikuler_id' => $ekstrakurikuler->id];

            if ($nilai === null) {
                NilaiEkstrakurikuler::query()->where($kriteria)->delete();

                continue;
            }

            NilaiEkstrakurikuler::query()->updateOrCreate($kriteria, ['nilai' => $nilai]);
        }
    }

    protected static function semester(mixed $state): ?int
    {
        $teks = Str::lower(trim((string) $state));

        return match (true) {
            $teks === '' => null,
            str_contains($teks, 'ganjil') || in_array($teks, ['1', 'i'], true) => Semester::Ganjil->value,
            str_contains($teks, 'genap') || in_array($teks, ['2', 'ii'], true) => Semester::Genap->value,
            default => is_numeric($teks) ? (int) $teks : 0,
        };
    }

    protected static function predikat(mixed $state): ?string
    {
        $teks = Str::upper(trim((string) $state));

        return $teks === '' ? null : $teks;
    }

    protected static function keputusan(mixed $state): ?string
    {
        $teks = Str::lower(trim((string) $state));

        return match (true) {
            $teks === '' => null,
            str_starts_with($teks, 'naik') => KeputusanKenaikan::Naik->value,
            str_starts_with($teks, 'tinggal') => KeputusanKenaikan::TinggalDiKelas->value,
            str_starts_with($teks, 'lulus') => KeputusanKenaikan::Lulus->value,
            default => $teks,
        };
    }
}
