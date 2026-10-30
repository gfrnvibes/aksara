<?php

namespace App\Models;

use App\Models\Concerns\CatatAktivitas;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Database\Factories\PenandaTanganFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An official who may sign the madrasah's letters: the principal, a vice principal, the head of administration.
 *
 * @property int $id
 * @property string $nama
 * @property string $jabatan
 * @property string|null $nip
 * @property int $urutan
 * @property bool $is_aktif
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['nama', 'jabatan', 'nip', 'urutan', 'is_aktif'])]
class PenandaTangan extends Model implements ProvidesActivityTitle
{
    use CatatAktivitas;

    /** @use HasFactory<PenandaTanganFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'is_aktif' => 'boolean',
        ];
    }

    /**
     * Signers offered for new letters, in the order they are listed.
     *
     * @param  Builder<PenandaTangan>  $query
     */
    #[Scope]
    protected function aktifBerurutan(Builder $query): void
    {
        $query->where('is_aktif', true)->orderBy('urutan')->orderBy('nama');
    }

    /**
     * Titles of the principal, who signs every letter from the academic year's data and is therefore never an
     * additional signer.
     *
     * @var list<string>
     */
    public const array JABATAN_KEPALA = ['kepala madrasah', 'plt. kepala madrasah', 'plt kepala madrasah'];

    public static function adalahJabatanKepala(?string $jabatan): bool
    {
        return in_array(mb_strtolower(trim((string) $jabatan)), self::JABATAN_KEPALA, true);
    }

    /**
     * Signers other than the principal.
     *
     * @param  Builder<PenandaTangan>  $query
     */
    #[Scope]
    protected function bukanKepalaMadrasah(Builder $query): void
    {
        $query->whereRaw('lower(trim(jabatan)) not in ('.implode(', ', array_fill(0, count(self::JABATAN_KEPALA), '?')).')', self::JABATAN_KEPALA);
    }

    public function label(): string
    {
        return "{$this->jabatan} - {$this->nama}";
    }

    public function activityTitle(): ?string
    {
        return $this->label();
    }
}
