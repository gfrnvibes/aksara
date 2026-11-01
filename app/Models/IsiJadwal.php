<?php

namespace App\Models;

use App\Enums\Hari;
use Database\Factories\IsiJadwalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One lesson period of a class in a timetable, given to one teaching assignment. A locked period was placed by
 * hand and stays where it is when the timetable is rebuilt automatically.
 *
 * @property int $id
 * @property int $jadwal_id
 * @property int $kelas_id
 * @property int $pembagian_mengajar_id
 * @property Hari $hari
 * @property int $jp_ke
 * @property bool $is_terkunci
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Jadwal $jadwal
 * @property-read Kelas $kelas
 * @property-read PembagianMengajar $pembagianMengajar
 */
#[Fillable(['jadwal_id', 'kelas_id', 'pembagian_mengajar_id', 'hari', 'jp_ke', 'is_terkunci'])]
class IsiJadwal extends Model
{
    /** @use HasFactory<IsiJadwalFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hari' => Hari::class,
            'jp_ke' => 'integer',
            'is_terkunci' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Jadwal, $this>
     */
    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(Jadwal::class);
    }

    /**
     * @return BelongsTo<Kelas, $this>
     */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    /**
     * @return BelongsTo<PembagianMengajar, $this>
     */
    public function pembagianMengajar(): BelongsTo
    {
        return $this->belongsTo(PembagianMengajar::class);
    }
}
