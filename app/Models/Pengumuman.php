<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $judul
 * @property string $isi
 * @property string $tujuan
 * @property bool $is_aktif
 * @property Carbon|null $tanggal_mulai
 * @property Carbon|null $tanggal_selesai
 * @property int|null $pembuat_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $pembuat
 */
#[Fillable(['judul', 'isi', 'tujuan', 'is_aktif', 'tanggal_mulai', 'tanggal_selesai', 'pembuat_id'])]
class Pengumuman extends Model
{
    protected $table = 'pengumumans';

    /** @var array<string, string> */
    public const array TUJUAN = [
        'guru' => 'Guru / GTK',
        'siswa' => 'Siswa / Orang Tua',
        'semua' => 'Guru & Siswa',
    ];

    protected function casts(): array
    {
        return [
            'is_aktif' => 'boolean',
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pembuat_id');
    }

    /**
     * @param  Builder<Pengumuman>  $query
     */
    #[Scope]
    protected function aktifUntuk(Builder $query, string $role): void
    {
        $today = now('Asia/Jakarta')->toDateString();

        $query->where('is_aktif', true)
            ->whereIn('tujuan', [$role, 'semua'])
            ->where(fn (Builder $q) => $q->whereNull('tanggal_mulai')->orWhereDate('tanggal_mulai', '<=', $today))
            ->where(fn (Builder $q) => $q->whereNull('tanggal_selesai')->orWhereDate('tanggal_selesai', '>=', $today));
    }
}
