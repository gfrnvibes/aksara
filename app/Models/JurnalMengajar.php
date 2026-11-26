<?php

namespace App\Models;

use App\Models\Concerns\CatatAktivitas;
use App\Models\Concerns\TanggalHarian;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** @property int $id
 * @property int $guru_id
 * @property int $pencatat_id
 * @property int|null $pelaksana_id
 * @property int $kelas_id
 * @property string $sumber_jadwal
 * @property string $status
 * @property Carbon $tanggal
 * @property-read Gtk|null $pelaksana
 * @property-read User $pencatat
 */
#[Guarded(['id'])]
class JurnalMengajar extends Model
{
    use CatatAktivitas;
    use TanggalHarian;

    public const array STATUS = ['hadir' => 'Hadir, jurnal belum lengkap', 'terlaksana' => 'Terlaksana', 'digantikan' => 'Digantikan', 'tidak_terlaksana' => 'Tidak terlaksana', 'kegiatan_pengganti' => 'Kegiatan pengganti'];

    /** @var array<string, string> */
    public const array METODE = [
        'ceramah' => 'Ceramah',
        'diskusi' => 'Diskusi / Kerja Kelompok',
        'demonstrasi' => 'Demonstrasi / Praktik',
        'simulasi' => 'Simulasi / Studi Kasus',
        'belajar_mandiri' => 'Belajar Mandiri / Penugasan',
        'tutorial' => 'Tutorial / Pendampingan',
    ];

    /** @var array<string, string> */
    public const array MEDIA = [
        'buku_teks' => 'Buku Teks / Cetak',
        'digital_tik' => 'Media Digital / TIK / E-Book',
        'alat_peraga' => 'Alat Peraga Non-Digital',
        'papan_tulis' => 'Papan Tulis / LKS',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'hadir_pada' => 'datetime', 'dibantu_ai' => 'boolean', 'pakai_buku_teks' => 'boolean'];
    }

    /** @return BelongsTo<Gtk, $this> */
    public function pelaksana(): BelongsTo
    {
        return $this->belongsTo(Gtk::class, 'pelaksana_id');
    }

    /** @return BelongsTo<User, $this> */
    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pencatat_id');
    }
}
