<?php

namespace App\Models;

use App\Enums\JenisGtk;
use App\Enums\JenisKelamin;
use App\Enums\StatusKepegawaian;
use App\Models\Concerns\CatatAktivitas;
use App\Models\Concerns\MenolakHapusSaatDipakai;
use App\Models\Contracts\TerlindungDariHapus;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Database\Factories\GtkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A teacher or member of staff (Guru dan Tenaga Kependidikan), mirroring the data kept in EMIS GTK.
 *
 * @property int $id
 * @property int|null $user_id The login account that opens the teacher panel.
 * @property string $nama Full name with academic titles, as printed on documents.
 * @property int|null $nomor_kode The teacher's number in the timetable codes (13 → "13", "13 A").
 * @property string|null $nip
 * @property string|null $nuptk
 * @property string|null $peg_id
 * @property string|null $nik
 * @property JenisKelamin $jenis_kelamin
 * @property string|null $tempat_lahir
 * @property Carbon|null $tanggal_lahir
 * @property JenisGtk $jenis_gtk
 * @property StatusKepegawaian $status_kepegawaian
 * @property string|null $jabatan
 * @property string|null $pendidikan_terakhir
 * @property string|null $program_studi
 * @property bool $is_sertifikasi
 * @property Carbon|null $tmt_tugas
 * @property string|null $nomor_hp Stored as digits starting with 0, so it can be typed as a login.
 * @property string|null $email
 * @property string|null $alamat
 * @property bool $is_aktif
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'nama', 'nomor_kode', 'nip', 'nuptk', 'peg_id', 'nik', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'jenis_gtk',
    'status_kepegawaian', 'jabatan', 'pendidikan_terakhir', 'program_studi', 'is_sertifikasi', 'tmt_tugas',
    'nomor_hp', 'email', 'alamat', 'is_aktif',
])]
class Gtk extends Model implements ProvidesActivityTitle, TerlindungDariHapus
{
    use CatatAktivitas;

    /** @use HasFactory<GtkFactory> */
    use HasFactory;

    use MenolakHapusSaatDipakai;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jenis_kelamin' => JenisKelamin::class,
            'jenis_gtk' => JenisGtk::class,
            'status_kepegawaian' => StatusKepegawaian::class,
            'tanggal_lahir' => 'date',
            'tmt_tugas' => 'date',
            'is_sertifikasi' => 'boolean',
            'is_aktif' => 'boolean',
        ];
    }

    /**
     * A corrected name or NIP reaches the classes the GTK looks after and their signer titles, so new report cards
     * and letters print it. Letters already saved keep the copy they made.
     */
    protected static function booted(): void
    {
        static::updated(function (Gtk $gtk): void {
            if ($gtk->wasChanged(['nama', 'nip'])) {
                $gtk->kelasPerwalian()->update(['wali_kelas' => $gtk->nama, 'nip_wali_kelas' => $gtk->nip]);
                $gtk->penandaTangan()->update(['nama' => $gtk->nama, 'nip' => $gtk->nip]);
            }
        });
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function nomorHp(): Attribute
    {
        return Attribute::set(fn (?string $nomor): ?string => static::normalkanNomorHp($nomor));
    }

    /**
     * Keep only digits and write the country code as a leading 0: "+62 812-3456" becomes "08123456".
     */
    public static function normalkanNomorHp(?string $nomor): ?string
    {
        $angka = preg_replace('/\D+/', '', (string) $nomor);

        if ($angka === '') {
            return null;
        }

        return str_starts_with($angka, '62') ? '0'.substr($angka, 2) : $angka;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The classes this GTK is homeroom teacher of.
     *
     * @return HasMany<Kelas, $this>
     */
    public function kelasPerwalian(): HasMany
    {
        return $this->hasMany(Kelas::class, 'wali_kelas_id');
    }

    /**
     * @return HasMany<PembagianMengajar, $this>
     */
    public function pembagianMengajar(): HasMany
    {
        return $this->hasMany(PembagianMengajar::class);
    }

    /**
     * @return HasMany<PermintaanJadwal, $this>
     */
    public function permintaanJadwal(): HasMany
    {
        return $this->hasMany(PermintaanJadwal::class);
    }

    /**
     * The titles this GTK signs letters under.
     *
     * @return HasMany<PenandaTangan, $this>
     */
    public function penandaTangan(): HasMany
    {
        return $this->hasMany(PenandaTangan::class);
    }

    /**
     * @param  Builder<Gtk>  $query
     */
    #[Scope]
    protected function aktif(Builder $query): void
    {
        $query->where('is_aktif', true);
    }

    /** @return HasMany<GajiGuru, $this> */
    public function gaji(): HasMany
    {
        return $this->hasMany(GajiGuru::class);
    }

    public function keteranganPemakaian(): ?string
    {
        if (PresensiGtk::query()->where('gtk_id', $this->id)->exists() || PenugasanPiket::query()->where('gtk_id', $this->id)->exists()
            || JurnalMengajar::query()->where('guru_id', $this->id)->orWhere('pelaksana_id', $this->id)->exists() || CatatanPiket::query()->where('gtk_id', $this->id)->exists()) {
            return "{$this->nama} memiliki riwayat administrasi harian. Nonaktifkan GTK ini jika tidak bertugas lagi.";
        }
        if ($this->gaji()->exists()) {
            return "{$this->nama} memiliki riwayat penggajian. Nonaktifkan GTK ini jika tidak bertugas lagi.";
        }
        $jumlahMengajar = $this->pembagianMengajar()->count();

        return $jumlahMengajar === 0
            ? null
            : "{$this->nama} masih tercatat di {$jumlahMengajar} pembagian mengajar. Nonaktifkan GTK ini jika tidak bertugas lagi.";
    }

    public function activityTitle(): ?string
    {
        return $this->nama;
    }
}
