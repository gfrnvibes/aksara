<?php

namespace App\Models;

use App\Models\Concerns\CatatAktivitas;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $ekstrakurikuler_id
 * @property int $tahun_ajaran_id
 * @property int $pembina_id
 * @property Carbon $tanggal
 * @property string $materi
 * @property int $jumlah_hadir
 * @property string|null $catatan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Ekstrakurikuler $ekstrakurikuler
 * @property-read TahunAjaran $tahunAjaran
 * @property-read Gtk $pembina
 */
#[Guarded(['id'])]
class KegiatanOrganisasi extends Model implements ProvidesActivityTitle
{
    use CatatAktivitas;

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'jumlah_hadir' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Ekstrakurikuler, $this>
     */
    public function ekstrakurikuler(): BelongsTo
    {
        return $this->belongsTo(Ekstrakurikuler::class);
    }

    /**
     * @return BelongsTo<TahunAjaran, $this>
     */
    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    /**
     * @return BelongsTo<Gtk, $this>
     */
    public function pembina(): BelongsTo
    {
        return $this->belongsTo(Gtk::class, 'pembina_id');
    }

    public function activityTitle(): ?string
    {
        return $this->ekstrakurikuler->nama.' — '.$this->materi;
    }
}
