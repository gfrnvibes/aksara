<?php

namespace App\Services\Cbt;

use App\Enums\Semester;
use App\Models\AlurTujuanPembelajaran;
use App\Models\AlurTujuanPembelajaranItem;
use App\Models\Gtk;
use App\Models\Kelas;
use App\Models\PerangkatAjar;
use App\Models\ProgramSemester;
use App\Models\ProgramSemesterItem;
use App\Models\ProgramTahunan;
use App\Models\ProgramTahunanItem;
use App\Models\TahunAjaran;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Loads concise topic + learning objective pairs from Perangkat Ajar (PROSEM, PROTA, ATP)
 * so AI question generation can sync directly without sending bloated CP/module text.
 */
class SumberMateriPerangkatAjar
{
    public const string SUMBER_PERANGKAT_AJAR = 'perangkat_ajar';

    public const string SUMBER_PROSEM = 'prosem';

    public const string SUMBER_PROTA = 'prota';

    public const string SUMBER_ATP = 'atp';

    /**
     * @return array<string, string>
     */
    public static function pilihanSumber(): array
    {
        return [
            self::SUMBER_PERANGKAT_AJAR => 'Perangkat Ajar (Generate AI)',
            self::SUMBER_PROSEM => 'Program Semester (PROSEM)',
            self::SUMBER_PROTA => 'Program Tahunan (PROTA)',
            self::SUMBER_ATP => 'Alur Tujuan Pembelajaran (ATP)',
        ];
    }

    /**
     * Matching Perangkat Ajar documents for a subject and grade in the active academic year,
     * with the current teacher's own documents listed first.
     *
     * @return array<int, string>
     */
    public static function pilihanDokumen(
        string $sumber,
        int $mapelId,
        int $tingkat,
        ?int $semester = null,
        ?int $gtkId = null,
    ): array {
        $tahunAjaranId = TahunAjaran::query()->where('is_aktif', true)->value('id');

        $query = match ($sumber) {
            self::SUMBER_PERANGKAT_AJAR => PerangkatAjar::query()->whereNotNull('master_tp'),
            self::SUMBER_PROSEM => ProgramSemester::query()
                ->when($semester !== null, fn (Builder $q): Builder => $q->where('semester', $semester)),
            self::SUMBER_PROTA => ProgramTahunan::query(),
            self::SUMBER_ATP => AlurTujuanPembelajaran::query(),
            default => null,
        };

        if ($query === null) {
            return [];
        }

        $query->with(['kelas', 'gtk'])
            ->when($sumber !== self::SUMBER_PERANGKAT_AJAR, fn (Builder $q): Builder => $q->withCount('items'))
            ->where('mata_pelajaran_id', $mapelId)
            ->when($tahunAjaranId !== null, fn (Builder $q): Builder => $q->where('tahun_ajaran_id', $tahunAjaranId))
            ->whereHas('kelas', fn (Builder $q): Builder => $q->where('tingkat', $tingkat));

        if ($gtkId !== null) {
            $query->orderByRaw('CASE WHEN gtk_id = ? THEN 0 ELSE 1 END', [$gtkId]);
        }

        $dokumen = $query->latest('id')->get();
        $hasil = [];

        foreach ($dokumen as $doc) {
            $kelasRel = $doc->getRelation('kelas');
            $gtkRel = $doc->getRelation('gtk');
            $namaKelas = $kelasRel instanceof Kelas ? $kelasRel->nama : 'Kelas';
            $namaGuru = $gtkRel instanceof Gtk ? $gtkRel->nama : 'Guru';

            if ($sumber === self::SUMBER_PERANGKAT_AJAR) {
                $jumlah = count(self::butirPerangkatAjar($doc, $semester));
                if ($jumlah === 0) {
                    continue;
                }
            } else {
                $jumlah = (int) ($doc->getAttribute('items_count') ?? 0);
            }

            $extra = '';
            if ($sumber === self::SUMBER_PROSEM) {
                $semValue = (int) $doc->getAttribute('semester');
                $extra = ' · '.(Semester::tryFrom($semValue)?->getLabel() ?? "Semester {$semValue}");
            }

            $hasil[$doc->id] = "{$namaKelas}{$extra} · {$namaGuru} ({$jumlah} materi)";
        }

        return $hasil;
    }

    /**
     * Returns ordered items with concise `materi` (<=255 chars) and `indikator` (<=1000 chars).
     *
     * @return array<int, array{id: int, materi: string, indikator: string}>
     */
    public static function daftarMateri(string $sumber, int $dokumenId, ?int $semester = null): array
    {
        $rows = match ($sumber) {
            self::SUMBER_PERANGKAT_AJAR => collect(self::butirPerangkatAjar(PerangkatAjar::query()->find($dokumenId), $semester)),
            self::SUMBER_PROSEM => ProgramSemesterItem::query()
                ->where('program_semester_id', $dokumenId)
                ->orderBy('urutan')
                ->get(['id', 'materi_pokok', 'tujuan_pembelajaran'])
                ->map(fn (ProgramSemesterItem $item): array => [
                    'id' => $item->id,
                    'materi' => trim($item->materi_pokok),
                    'indikator' => trim($item->tujuan_pembelajaran),
                ]),
            self::SUMBER_PROTA => ProgramTahunanItem::query()
                ->where('program_tahunan_id', $dokumenId)
                ->when($semester !== null, fn (Builder $q): Builder => $q->where('semester', $semester))
                ->orderBy('semester')
                ->orderBy('urutan')
                ->get(['id', 'materi_pokok', 'tujuan_pembelajaran'])
                ->map(fn (ProgramTahunanItem $item): array => [
                    'id' => $item->id,
                    'materi' => trim($item->materi_pokok),
                    'indikator' => trim($item->tujuan_pembelajaran),
                ]),
            self::SUMBER_ATP => AlurTujuanPembelajaranItem::query()
                ->where('alur_tujuan_pembelajaran_id', $dokumenId)
                ->orderBy('urutan')
                ->get(['id', 'materi_pembelajaran', 'tujuan_pembelajaran'])
                ->map(fn (AlurTujuanPembelajaranItem $item): array => [
                    'id' => $item->id,
                    'materi' => trim($item->materi_pembelajaran),
                    'indikator' => trim($item->tujuan_pembelajaran),
                ]),
            default => collect(),
        };

        $hasil = [];

        foreach ($rows as $row) {
            if ($row['materi'] === '') {
                continue;
            }

            $hasil[$row['id']] = [
                'id' => $row['id'],
                'materi' => Str::limit($row['materi'], 255, ''),
                'indikator' => Str::limit($row['indikator'], 1000, ''),
            ];
        }

        return $hasil;
    }

    /**
     * @return list<array{id: int, materi: string, indikator: string}>
     */
    private static function butirPerangkatAjar(?Model $perangkat, ?int $semester = null): array
    {
        $masterTp = $perangkat?->getAttribute('master_tp');
        if (! is_array($masterTp)) {
            return [];
        }

        $hasil = [];

        foreach (array_values($masterTp) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $itemSemester = isset($item['semester']) ? (int) $item['semester'] : null;
            if ($semester !== null && $itemSemester !== null && $itemSemester !== $semester) {
                continue;
            }

            $materi = trim((string) ($item['materi_pokok'] ?? ''));
            if ($materi === '') {
                continue;
            }

            $hasil[] = [
                'id' => $index + 1,
                'materi' => $materi,
                'indikator' => trim((string) ($item['tujuan_pembelajaran'] ?? '')),
            ];
        }

        return $hasil;
    }
}
