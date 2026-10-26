<?php

namespace App\Services\CatatanWaliKelas;

use App\Enums\KeputusanKenaikan;
use App\Enums\Semester;
use App\Filament\Resources\Kelas\KelasResource;
use App\Models\Ekstrakurikuler;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Siswa;

/**
 * What a homeroom note is written from: one student's grades, attitude, activities and attendance for a semester.
 *
 * It never holds the student's name, NIS or class, so it can be sent to an AI provider as is.
 */
final readonly class RingkasanRapor
{
    /**
     * @param  list<array{nama: string, kkm: int, pengetahuan: int|null, keterampilan: int|null, sikap: string|null}>  $mataPelajaran
     * @param  list<array{nama: string, nilai: string|null}>  $ekstrakurikuler
     * @param  KeputusanKenaikan|null  $keputusan  The end-of-year decision; only set on an even-semester report card.
     * @param  int|null  $naikKeTingkat  The grade (7-9) the student moves up to when promoted.
     */
    public function __construct(
        public array $mataPelajaran,
        public array $ekstrakurikuler,
        public ?string $akhlak,
        public ?string $kepribadian,
        public int $sakit,
        public int $izin,
        public int $tanpaKeterangan,
        public ?KeputusanKenaikan $keputusan = null,
        public ?int $naikKeTingkat = null,
    ) {}

    /**
     * Build the summary from the report-card form state, which may still be unsaved.
     *
     * @param  array<string, mixed>  $data
     */
    public static function dariForm(array $data): self
    {
        $barisMapel = array_values(array_filter((array) ($data['nilaiRapor'] ?? []), 'is_array'));
        $namaMapel = MataPelajaran::query()->whereIn('id', array_column($barisMapel, 'mata_pelajaran_id'))->pluck('nama', 'id');

        $barisEkskul = array_values(array_filter((array) ($data['nilaiEkstrakurikuler'] ?? []), 'is_array'));
        $namaEkskul = Ekstrakurikuler::query()->whereIn('id', array_column($barisEkskul, 'ekstrakurikuler_id'))->pluck('nama', 'id');

        $mataPelajaran = [];

        foreach ($barisMapel as $baris) {
            $nama = $namaMapel[$baris['mata_pelajaran_id'] ?? null] ?? null;

            if ($nama === null) {
                continue;
            }

            $mataPelajaran[] = [
                'nama' => $nama,
                'kkm' => (int) ($baris['kkm'] ?? 0),
                'pengetahuan' => self::angka($baris['nilai_pengetahuan'] ?? null),
                'keterampilan' => self::angka($baris['nilai_keterampilan'] ?? null),
                'sikap' => self::teks($baris['sikap'] ?? null),
            ];
        }

        $ekstrakurikuler = [];

        foreach ($barisEkskul as $baris) {
            $nama = $namaEkskul[$baris['ekstrakurikuler_id'] ?? null] ?? null;

            if ($nama !== null) {
                $ekstrakurikuler[] = ['nama' => $nama, 'nilai' => self::teks($baris['nilai'] ?? null)];
            }
        }

        $keputusan = self::semester($data['semester'] ?? null) === Semester::Genap
            ? self::keputusan($data['keputusan'] ?? null)
            : null;

        $tingkatSekarang = $keputusan === KeputusanKenaikan::Naik && filled($data['kelas_id'] ?? null)
            ? Kelas::query()->whereKey($data['kelas_id'])->value('tingkat')
            : null;

        return new self(
            mataPelajaran: $mataPelajaran,
            ekstrakurikuler: $ekstrakurikuler,
            akhlak: self::teks($data['nilai_akhlak'] ?? null),
            kepribadian: self::teks($data['nilai_kepribadian'] ?? null),
            sakit: (int) ($data['sakit'] ?? 0),
            izin: (int) ($data['izin'] ?? 0),
            tanpaKeterangan: (int) ($data['tanpa_keterangan'] ?? 0),
            keputusan: $keputusan,
            naikKeTingkat: $tingkatSekarang !== null ? (int) $tingkatSekarang + 1 : null,
        );
    }

    /**
     * The grade the student moves up to in Roman numerals (e.g. "VIII"), when known.
     */
    public function kelasTujuan(): ?string
    {
        return $this->naikKeTingkat === null ? null : (KelasResource::getTingkatOptions()[$this->naikKeTingkat] ?? null);
    }

    /**
     * How the note addresses the student: "Ananda" plus the nickname when one is recorded.
     */
    public static function sapaanUntuk(mixed $siswaId): string
    {
        $panggilan = filled($siswaId) ? Siswa::query()->whereKey($siswaId)->value('nama_panggilan') : null;

        return filled($panggilan) ? "Ananda {$panggilan}" : 'Ananda';
    }

    /**
     * Subjects that have at least one score, each with the mean of its knowledge and skill scores.
     *
     * @return list<array{nama: string, kkm: int, pengetahuan: int|null, keterampilan: int|null, sikap: string|null, rata: float}>
     */
    public function mataPelajaranBernilai(): array
    {
        $hasil = [];

        foreach ($this->mataPelajaran as $mapel) {
            $nilai = array_filter([$mapel['pengetahuan'], $mapel['keterampilan']], fn (?int $angka): bool => $angka !== null);

            if ($nilai !== []) {
                $hasil[] = [...$mapel, 'rata' => array_sum($nilai) / count($nilai)];
            }
        }

        return $hasil;
    }

    public function rataRata(): ?float
    {
        $mapel = $this->mataPelajaranBernilai();

        return $mapel === [] ? null : round(array_sum(array_column($mapel, 'rata')) / count($mapel), 1);
    }

    /**
     * Mean of one score type across subjects, or null when no subject has it.
     */
    public function rataRataJenis(string $jenis): ?float
    {
        $nilai = array_filter(array_column($this->mataPelajaran, $jenis), is_int(...));

        return $nilai === [] ? null : array_sum($nilai) / count($nilai);
    }

    /**
     * The data an AI provider may see: grades and attendance only, no identity.
     *
     * @return array<string, mixed>
     */
    public function untukAi(): array
    {
        return [
            'mata_pelajaran' => $this->mataPelajaran,
            'rata_rata' => $this->rataRata(),
            'ekstrakurikuler' => $this->ekstrakurikuler,
            'akhlak' => $this->akhlak,
            'kepribadian' => $this->kepribadian,
            'ketidakhadiran_hari' => [
                'sakit' => $this->sakit,
                'izin' => $this->izin,
                'tanpa_keterangan' => $this->tanpaKeterangan,
            ],
            'keputusan_akhir_tahun' => $this->keputusan === null ? null : [
                'keputusan' => $this->keputusan->getLabel(),
                'naik_ke_kelas' => $this->kelasTujuan(),
            ],
        ];
    }

    private static function semester(mixed $nilai): ?Semester
    {
        return $nilai instanceof Semester ? $nilai : Semester::tryFrom((int) $nilai);
    }

    private static function keputusan(mixed $nilai): ?KeputusanKenaikan
    {
        return $nilai instanceof KeputusanKenaikan ? $nilai : KeputusanKenaikan::tryFrom((string) $nilai);
    }

    private static function angka(mixed $nilai): ?int
    {
        return is_numeric($nilai) ? (int) $nilai : null;
    }

    private static function teks(mixed $nilai): ?string
    {
        return filled($nilai) ? strtoupper(trim((string) $nilai)) : null;
    }
}
