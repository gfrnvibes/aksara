<?php

namespace App\Models;

use App\Enums\SifatSurat;
use App\Enums\StatusSuratMasuk;
use App\Models\Concerns\CatatAktivitas;
use App\Models\Concerns\MemilikiBerkasArsip;
use App\Services\Surat\PenomoranSurat;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Database\Factories\SuratMasukFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An incoming letter: its scan on the archive disk, its number in the year's agenda, and where it was passed on.
 *
 * @property int $id
 * @property int $tahun
 * @property int $nomor_agenda
 * @property Carbon $tanggal_diterima
 * @property string|null $nomor_surat
 * @property Carbon|null $tanggal_surat
 * @property string $pengirim
 * @property string $perihal
 * @property SifatSurat $sifat
 * @property string|null $ringkasan
 * @property list<string>|null $berkas Paths on the archive disk, in page order.
 * @property StatusSuratMasuk $status
 * @property string|null $disposisi_kepada
 * @property string|null $disposisi_instruksi
 * @property Carbon|null $disposisi_tanggal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tanggal_diterima', 'nomor_surat', 'tanggal_surat', 'pengirim', 'perihal', 'sifat', 'ringkasan', 'berkas', 'status',
    'disposisi_kepada', 'disposisi_instruksi', 'disposisi_tanggal',
])]
class SuratMasuk extends Model implements ProvidesActivityTitle
{
    use CatatAktivitas;

    /** @use HasFactory<SuratMasukFactory> */
    use HasFactory;

    use MemilikiBerkasArsip;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'nomor_agenda' => 'integer',
            'tanggal_diterima' => 'date',
            'tanggal_surat' => 'date',
            'disposisi_tanggal' => 'date',
            'sifat' => SifatSurat::class,
            'status' => StatusSuratMasuk::class,
            'berkas' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (SuratMasuk $surat) => app(PenomoranSurat::class)->beriNomorAgenda($surat));
    }

    /**
     * The agenda number as written in the register, e.g. "007/2026".
     */
    public function labelAgenda(): string
    {
        return str_pad((string) $this->nomor_agenda, 3, '0', STR_PAD_LEFT)."/{$this->tahun}";
    }

    public function activityTitle(): ?string
    {
        return "Agenda {$this->labelAgenda()} - {$this->perihal}";
    }
}
