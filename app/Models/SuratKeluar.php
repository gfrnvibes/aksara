<?php

namespace App\Models;

use App\Enums\JenisSuratKeluar;
use App\Enums\SifatSurat;
use App\Models\Concerns\CatatAktivitas;
use App\Services\Surat\IsianSurat;
use App\Services\Surat\PenomoranSurat;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Carbon\CarbonInterface;
use Database\Factories\SuratKeluarFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An outgoing letter, signed by the principal of its academic year and, when chosen, another official. It is
 * numbered once, when first saved, and keeps the signers' names as they were, so a printed copy stays the same
 * after an official changes.
 *
 * @property int $id
 * @property int $tahun
 * @property int $nomor_urut
 * @property string $nomor
 * @property JenisSuratKeluar $jenis
 * @property SifatSurat|null $sifat
 * @property bool $gunakan_sifat
 * @property bool $tanda_tangan_kepala_madrasah
 * @property CarbonInterface $tanggal
 * @property string $perihal
 * @property string $tujuan
 * @property string|null $lampiran
 * @property string|null $isi Letter body as HTML; may hold placeholders such as {nama_siswa}.
 * @property int|null $siswa_id
 * @property int|null $penanda_tangan_id
 * @property string|null $nama_kepala_madrasah
 * @property string|null $nip_kepala_madrasah
 * @property string|null $nama_penanda_tangan
 * @property string|null $jabatan_penanda_tangan
 * @property string|null $nip_penanda_tangan
 * @property int|null $dibuat_oleh
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['jenis', 'sifat', 'gunakan_sifat', 'tanda_tangan_kepala_madrasah', 'tanggal', 'perihal', 'tujuan', 'lampiran', 'isi', 'siswa_id', 'penanda_tangan_id'])]
class SuratKeluar extends Model implements ProvidesActivityTitle
{
    use CatatAktivitas;

    /** @use HasFactory<SuratKeluarFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jenis' => JenisSuratKeluar::class,
            'sifat' => SifatSurat::class,
            'gunakan_sifat' => 'boolean',
            'tanda_tangan_kepala_madrasah' => 'boolean',
            'tanggal' => 'date',
            'tahun' => 'integer',
            'nomor_urut' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SuratKeluar $surat): void {
            $surat->dibuat_oleh ??= auth()->id() === null ? null : (int) auth()->id();

            app(PenomoranSurat::class)->beriNomorSuratKeluar($surat);
        });

        static::saving(function (SuratKeluar $surat): void {
            if (! $surat->exists || $surat->isDirty('tanggal')) {
                $surat->salinKepalaMadrasah();
            }

            if ($surat->isDirty('penanda_tangan_id')) {
                $surat->salinPenandaTanganTambahan();
            }
        });
    }

    /**
     * Copy the principal of the letter's academic year onto the letter.
     */
    public function salinKepalaMadrasah(): void
    {
        $tahunAjaran = TahunAjaran::untukTanggal($this->tanggal);

        $this->nama_kepala_madrasah = $tahunAjaran?->kepala_madrasah;
        $this->nip_kepala_madrasah = $tahunAjaran?->nip_kepala_madrasah;
    }

    /**
     * Copy the chosen additional signer onto the letter, or clear it when none is chosen.
     */
    public function salinPenandaTanganTambahan(): void
    {
        $penandaTangan = $this->penanda_tangan_id === null ? null : PenandaTangan::query()->find($this->penanda_tangan_id);

        $this->nama_penanda_tangan = $penandaTangan?->nama;
        $this->jabatan_penanda_tangan = $penandaTangan?->jabatan;
        $this->nip_penanda_tangan = $penandaTangan?->nip;
    }

    /**
     * The principal who signs the letter: the one copied when it was saved, or the academic year's current one for
     * a letter saved before the principal was recorded.
     *
     * @return array{nama: string|null, nip: string|null}
     */
    public function kepalaMadrasah(): array
    {
        if (filled($this->nama_kepala_madrasah)) {
            return ['nama' => $this->nama_kepala_madrasah, 'nip' => $this->nip_kepala_madrasah];
        }

        $tahunAjaran = TahunAjaran::untukTanggal($this->tanggal);

        return ['nama' => $tahunAjaran?->kepala_madrasah, 'nip' => $tahunAjaran?->nip_kepala_madrasah];
    }

    /**
     * The body ready to print: sanitized, with the student's details filled into the placeholders.
     */
    public function isiSiapCetak(): string
    {
        return IsianSurat::isi($this->isi, $this->siswa);
    }

    /**
     * A plain-text field, such as the subject or the addressee, with the student's details filled into its
     * placeholders.
     */
    public function teksSiapCetak(?string $teks): string
    {
        return IsianSurat::teks($teks, $this->siswa);
    }

    /**
     * The student the letter is about, even when moved to the trash.
     *
     * @return BelongsTo<Siswa, $this>
     */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class)->withTrashed();
    }

    /**
     * @return BelongsTo<PenandaTangan, $this>
     */
    public function penandaTangan(): BelongsTo
    {
        return $this->belongsTo(PenandaTangan::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function activityTitle(): ?string
    {
        return "{$this->nomor} - {$this->perihal}";
    }
}
