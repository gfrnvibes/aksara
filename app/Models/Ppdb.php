<?php

namespace App\Models;

use App\Enums\Agama;
use App\Enums\JenisKelamin;
use App\Enums\StatusPpdb;
use App\Models\Concerns\CatatAktivitas;
use App\Models\Concerns\MemilikiBerkasArsip;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Database\Factories\PpdbFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A new-student registration for one academic year. It is numbered per year when first saved, moves through the
 * committee's checks, and points at the student it became once entered into the Buku Induk.
 *
 * @property int $id
 * @property int $tahun_ajaran_id
 * @property int $nomor_urut
 * @property string $nomor
 * @property StatusPpdb $status
 * @property string|null $catatan
 * @property string $nama_lengkap
 * @property string $nisn
 * @property string $nik
 * @property string $tempat_lahir
 * @property Carbon $tanggal_lahir
 * @property JenisKelamin $jenis_kelamin
 * @property Agama|null $agama
 * @property string|null $asal_sekolah
 * @property string $alamat
 * @property string $nomor_telepon
 * @property string|null $nomor_kip_pip
 * @property string $nama_ayah_kandung
 * @property string $nama_ibu_kandung
 * @property string|null $nama_wali
 * @property array<string, string>|null $berkas Paths on the archive disk, keyed by document (see BERKAS).
 * @property int|null $siswa_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tahun_ajaran_id', 'status', 'catatan', 'nama_lengkap', 'nisn', 'nik', 'tempat_lahir', 'tanggal_lahir',
    'jenis_kelamin', 'agama', 'asal_sekolah', 'alamat', 'nomor_telepon', 'nomor_kip_pip', 'nama_ayah_kandung',
    'nama_ibu_kandung', 'nama_wali', 'berkas',
])]
class Ppdb extends Model implements ProvidesActivityTitle
{
    use CatatAktivitas;

    /** @use HasFactory<PpdbFactory> */
    use HasFactory;

    use MemilikiBerkasArsip;

    protected $table = 'ppdbs';

    /**
     * Documents an applicant hands in, keyed as stored in `berkas`.
     *
     * @var array<string, string>
     */
    public const array BERKAS = [
        'kk' => 'Kartu Keluarga',
        'akta' => 'Akta Kelahiran',
        'ijazah' => 'Ijazah / SKL',
        'foto' => 'Pas Foto',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatusPpdb::class,
            'jenis_kelamin' => JenisKelamin::class,
            'agama' => Agama::class,
            'tanggal_lahir' => 'date',
            'nomor_urut' => 'integer',
            'berkas' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Ppdb $ppdb): void {
            $ppdb->tahun_ajaran_id ??= TahunAjaran::untukPpdb()?->id;
            $ppdb->status ??= StatusPpdb::Menunggu;
            $ppdb->beriNomor();
        });
    }

    /**
     * The next registration number of the academic year, e.g. "SPMB-2027-004".
     */
    public function beriNomor(): void
    {
        $tahunAjaran = TahunAjaran::query()->findOrFail($this->tahun_ajaran_id);

        $this->nomor_urut = (int) static::query()->where('tahun_ajaran_id', $tahunAjaran->id)->max('nomor_urut') + 1;
        $this->nomor = 'SPMB-'.substr($tahunAjaran->nama, 0, 4).'-'.str_pad((string) $this->nomor_urut, 3, '0', STR_PAD_LEFT);
    }

    /**
     * The uploaded documents with their names and short-lived links, in the order of BERKAS, for the preview modal.
     *
     * @return list<array{url: string, pdf: bool, nama: string, judul: string}>
     */
    public function berkasUntukPratinjau(): array
    {
        $daftar = [];

        foreach (self::BERKAS as $kunci => $judul) {
            if (filled($path = $this->berkas[$kunci] ?? null)) {
                $daftar[] = [
                    'url' => $this->tautanSementara($path),
                    'pdf' => static::adalahPdf($path),
                    'nama' => basename($path),
                    'judul' => $judul,
                ];
            }
        }

        return $daftar;
    }

    /**
     * @return BelongsTo<TahunAjaran, $this>
     */
    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    /**
     * The student this registration became, even when moved to the trash.
     *
     * @return BelongsTo<Siswa, $this>
     */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class)->withTrashed();
    }

    public function activityTitle(): ?string
    {
        return "{$this->nomor} - {$this->nama_lengkap}";
    }
}
