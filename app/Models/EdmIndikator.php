<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $edm_evaluasi_id
 * @property string $aspek
 * @property string $kode
 * @property string $pertanyaan
 * @property int|null $skor_saran
 * @property int|null $skor_final
 * @property array<string, mixed>|null $metrik_sistem
 * @property array<string, mixed>|null $rekap_tabel
 * @property string|null $kondisi_nyata
 * @property string|null $tindak_lanjut
 * @property bool $dibantu_ai
 * @property Carbon|null $terakhir_sinkron_at
 * @property int|null $pengisi_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EdmEvaluasi $evaluasi
 * @property-read User|null $pengisi
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EdmBukti> $buktis
 */
#[Fillable([
    'edm_evaluasi_id',
    'aspek',
    'kode',
    'pertanyaan',
    'skor_saran',
    'skor_final',
    'metrik_sistem',
    'rekap_tabel',
    'kondisi_nyata',
    'tindak_lanjut',
    'dibantu_ai',
    'terakhir_sinkron_at',
    'pengisi_id',
])]
class EdmIndikator extends Model
{
    protected function casts(): array
    {
        return [
            'skor_saran' => 'integer',
            'skor_final' => 'integer',
            'metrik_sistem' => 'array',
            'rekap_tabel' => 'array',
            'dibantu_ai' => 'boolean',
            'terakhir_sinkron_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<EdmEvaluasi, $this> */
    public function evaluasi(): BelongsTo
    {
        return $this->belongsTo(EdmEvaluasi::class, 'edm_evaluasi_id');
    }

    /** @return BelongsTo<User, $this> */
    public function pengisi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengisi_id');
    }

    /** @return HasMany<EdmBukti, $this> */
    public function buktis(): HasMany
    {
        return $this->hasMany(EdmBukti::class);
    }

    public static function diskName(): string
    {
        return (string) (config('filesystems.arsip') ?: 'local');
    }

    /**
     * Gabungkan bukti unggahan langsung (EdmBukti) dan foto/berkas dari GaleriKegiatan yang ditandai kode butir ini.
     *
     * @return Collection<int, array{sumber: string, judul: string, disk: string, path: string, url: string|null, is_gambar: bool, id_bukti: int|null}>
     */
    public function buktiGabungan(): Collection
    {
        $diskEdm = self::diskName();
        $items = collect();

        foreach ($this->buktis as $bukti) {
            $ext = strtolower(pathinfo($bukti->berkas_path, PATHINFO_EXTENSION));
            $items->push([
                'sumber' => 'upload',
                'judul' => $bukti->judul,
                'disk' => $diskEdm,
                'path' => $bukti->berkas_path,
                'url' => null,
                'is_gambar' => in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true),
                'id_bukti' => $bukti->id,
            ]);
        }

        $diskGaleri = GaleriKegiatan::disk();
        $galeris = GaleriKegiatan::query()
            ->whereJsonContains('kode_butir_edm', $this->kode)
            ->latest('tanggal_kegiatan')
            ->get();

        foreach ($galeris as $galeri) {
            foreach ($galeri->berkas ?? [] as $idx => $path) {
                $ext = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
                $items->push([
                    'sumber' => 'galeri',
                    'judul' => $galeri->judul.(count($galeri->berkas ?? []) > 1 ? ' #'.($idx + 1) : ''),
                    'disk' => $diskGaleri,
                    'path' => (string) $path,
                    'url' => null,
                    'is_gambar' => in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true),
                    'id_bukti' => null,
                ]);
            }
        }

        foreach ($this->buktiDariModulTerkait($diskEdm) as $item) {
            $items->push($item);
        }

        return $items;
    }

    /**
     * @return list<array{sumber: string, judul: string, disk: string, path: string, url: string|null, is_gambar: bool, id_bukti: int|null}>
     */
    protected function buktiDariModulTerkait(string $diskEdm): array
    {
        $tahunId = $this->evaluasi->tahun_ajaran_id ?? null;
        $hasil = [];

        $tambah = function (string $sumber, string $judul, ?string $path) use (&$hasil, $diskEdm): void {
            if (blank($path)) {
                return;
            }
            $ext = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
            $hasil[] = [
                'sumber' => $sumber,
                'judul' => $judul,
                'disk' => $diskEdm,
                'path' => (string) $path,
                'url' => null,
                'is_gambar' => in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true),
                'id_bukti' => null,
            ];
        };

        if (in_array($this->kode, ['A3', 'C4'], true) && $tahunId) {
            SupervisiPembelajaran::query()->with('gtk')->where('tahun_ajaran_id', $tahunId)->whereNotNull('berkas_path')->get()
                ->each(fn (SupervisiPembelajaran $s) => $tambah('supervisi', 'Supervisi '.($s->gtk->nama ?? ''), $s->berkas_path));
        }

        if ($this->kode === 'A4' && $tahunId) {
            PrestasiSiswa::query()->where('tahun_ajaran_id', $tahunId)->whereNotNull('berkas_path')->get()
                ->each(fn (PrestasiSiswa $p) => $tambah('prestasi', $p->nama_prestasi, $p->berkas_path));
        }

        if (in_array($this->kode, ['A7', 'E1'], true) && $tahunId) {
            RapatMadrasah::query()->where('tahun_ajaran_id', $tahunId)->whereNotNull('berkas_path')->get()
                ->each(fn (RapatMadrasah $r) => $tambah('rapat', $r->judul, $r->berkas_path));
        }

        if (in_array($this->kode, ['B1', 'B2', 'B4', 'E2'], true) && $tahunId) {
            PelatihanGtk::query()->where('tahun_ajaran_id', $tahunId)->whereNotNull('berkas_path')->get()
                ->each(fn (PelatihanGtk $p) => $tambah('pelatihan', $p->nama_kegiatan, $p->berkas_path));
        }

        if (in_array($this->kode, ['B3', 'E1'], true) && $tahunId) {
            ProgramRkam::query()->where('tahun_ajaran_id', $tahunId)->whereNotNull('berkas_path')->get()
                ->each(fn (ProgramRkam $r) => $tambah('rkam', $r->nama_program, $r->berkas_path));
        }

        if (in_array($this->kode, ['D2', 'D3', 'D5'], true)) {
            InventarisMedia::query()->whereNotNull('berkas_path')->get()
                ->each(fn (InventarisMedia $m) => $tambah('inventaris', $m->nama, $m->berkas_path));
        }

        return $hasil;
    }
}
