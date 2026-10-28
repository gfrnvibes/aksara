<?php

namespace App\Models;

use App\Enums\JenisMutasi;
use App\Enums\StatusSiswa;
use App\Models\Concerns\CatatAktivitas;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Database\Factories\MutasiSiswaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A student joining from, moving to, or leaving for another school. Recording it updates the student's status and
 * the Buku Induk fields about arriving or leaving; deleting it undoes a departure that is still in effect.
 *
 * @property int $id
 * @property int $siswa_id
 * @property JenisMutasi $jenis
 * @property Carbon $tanggal
 * @property int|null $tahun_ajaran_id
 * @property int|null $kelas_id
 * @property string|null $sekolah
 * @property string|null $alasan
 * @property string|null $nomor_surat
 * @property string|null $keterangan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['siswa_id', 'jenis', 'tanggal', 'tahun_ajaran_id', 'kelas_id', 'sekolah', 'alasan', 'nomor_surat', 'keterangan'])]
class MutasiSiswa extends Model implements ProvidesActivityTitle
{
    use CatatAktivitas;

    /** @use HasFactory<MutasiSiswaFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jenis' => JenisMutasi::class,
            'tanggal' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (MutasiSiswa $mutasi) => $mutasi->terapkanKeSiswa());

        static::deleted(fn (MutasiSiswa $mutasi) => $mutasi->batalkanDariSiswa());
    }

    /**
     * Write the transfer into the student's Buku Induk record.
     */
    public function terapkanKeSiswa(): void
    {
        $siswa = $this->siswa;

        if ($this->jenis->meninggalkanSekolah()) {
            $siswa->update([
                'status' => $this->jenis->statusSiswa(),
                'tanggal_meninggalkan_sekolah' => $this->tanggal,
                'alasan_meninggalkan_sekolah' => $this->alasanUntukBukuInduk(),
            ]);

            return;
        }

        $siswa->update([
            'status' => StatusSiswa::Aktif,
            'pindahan_dari' => $this->sekolah ?? $siswa->getAttribute('pindahan_dari'),
            'alasan_pindah' => $this->alasan ?? $siswa->getAttribute('alasan_pindah'),
            'tanggal_diterima' => $this->tanggal,
            'kelas_id' => $this->kelas_id ?? $siswa->kelas_id,
            'diterima_di_tingkat' => $this->kelas->tingkat ?? $siswa->getAttribute('diterima_di_tingkat'),
        ]);
    }

    /**
     * Make the student active again when the departure being deleted is the one their record still shows.
     */
    public function batalkanDariSiswa(): void
    {
        $siswa = Siswa::withTrashed()->find($this->siswa_id);

        if ($siswa === null) {
            return;
        }

        if (! $this->jenis->meninggalkanSekolah() || $siswa->status !== $this->jenis->statusSiswa()) {
            return;
        }

        $siswa->update([
            'status' => StatusSiswa::Aktif,
            'tanggal_meninggalkan_sekolah' => null,
            'alasan_meninggalkan_sekolah' => null,
        ]);
    }

    /**
     * The reason written in the Buku Induk, e.g. "Pindah ke MTs Negeri 1 Garut: ikut orang tua".
     */
    public function alasanUntukBukuInduk(): ?string
    {
        $tujuan = $this->jenis === JenisMutasi::Pindah && filled($this->sekolah) ? "Pindah ke {$this->sekolah}" : null;

        return collect([$tujuan, $this->alasan])->filter()->implode(': ') ?: null;
    }

    /**
     * @return BelongsTo<Siswa, $this>
     */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class)->withTrashed();
    }

    /**
     * @return BelongsTo<TahunAjaran, $this>
     */
    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    /**
     * @return BelongsTo<Kelas, $this>
     */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function activityTitle(): ?string
    {
        return "{$this->jenis->getLabel()} - {$this->siswa->nama_lengkap}";
    }
}
