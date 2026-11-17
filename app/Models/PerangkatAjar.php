<?php

namespace App\Models;

use App\Concerns\CatatAktivitas;
use App\Concerns\ProvidesActivityTitle;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tahun_ajaran_id',
    'kelas_id',
    'mata_pelajaran_id',
    'gtk_id',
    'satuan_pendidikan',
    'kepala_madrasah',
    'nip_kepala',
    'guru_pengampu',
    'nip_guru',
    'kota_titimangsa',
    'tanggal_titimangsa',
    'jenjang',
    'kelas',
    'fase',
    'tahun_pelajaran',
    'jp_per_minggu',
    'data_ame',
    'master_tp',
    'modul_ajars',
    'catatan',
])]
class PerangkatAjar extends Model implements ProvidesActivityTitle
{
    use CatatAktivitas;
    use HasFactory;

    protected $table = 'perangkat_ajars';

    protected function casts(): array
    {
        return [
            'tanggal_titimangsa' => 'date',
            'jp_per_minggu' => 'integer',
            'data_ame' => 'array',
            'master_tp' => 'array',
            'modul_ajars' => 'array',
        ];
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function gtk(): BelongsTo
    {
        return $this->belongsTo(Gtk::class);
    }

    public function activityTitle(): string
    {
        return "Perangkat Ajar {$this->mataPelajaran?->nama} Kelas {$this->kelas?->nama}";
    }

    /**
     * Hitung total minggu efektif Semester 1
     */
    public function hitungTotalMggEfektifSem1(): int
    {
        $bulanSem1 = $this->data_ame['semester_1'] ?? [];
        if (empty($bulanSem1)) {
            return (int) ($this->data_ame['ringkasan']['total_mgg_efektif_sem_1'] ?? 0);
        }

        return (int) collect($bulanSem1)->sum('mgg_efektif');
    }

    /**
     * Hitung total minggu efektif Semester 2
     */
    public function hitungTotalMggEfektifSem2(): int
    {
        $bulanSem2 = $this->data_ame['semester_2'] ?? [];
        if (empty($bulanSem2)) {
            return (int) ($this->data_ame['ringkasan']['total_mgg_efektif_sem_2'] ?? 0);
        }

        return (int) collect($bulanSem2)->sum('mgg_efektif');
    }

    /**
     * Hitung total JP AME Semester 1
     */
    public function hitungTotalJpAmeSem1(): int
    {
        if (isset($this->data_ame['ringkasan']['total_jp_sem_1'])) {
            return (int) $this->data_ame['ringkasan']['total_jp_sem_1'];
        }

        return $this->hitungTotalMggEfektifSem1() * ($this->jp_per_minggu ?: 5);
    }

    /**
     * Hitung total JP AME Semester 2
     */
    public function hitungTotalJpAmeSem2(): int
    {
        if (isset($this->data_ame['ringkasan']['total_jp_sem_2'])) {
            return (int) $this->data_ame['ringkasan']['total_jp_sem_2'];
        }

        return $this->hitungTotalMggEfektifSem2() * ($this->jp_per_minggu ?: 5);
    }

    /**
     * Hitung total JP AME Setahun
     */
    public function hitungTotalJpAme(): int
    {
        return $this->hitungTotalJpAmeSem1() + $this->hitungTotalJpAmeSem2();
    }

    /**
     * Hitung total JP dari seluruh TP di Semester 1
     */
    public function hitungTotalJpTpSem1(): int
    {
        $items = $this->master_tp ?? [];

        return (int) collect($items)
            ->filter(fn ($tp) => (int) ($tp['semester'] ?? 1) === 1)
            ->sum(fn ($tp) => (int) ($tp['alokasi_jp'] ?? 0));
    }

    /**
     * Hitung total JP dari seluruh TP di Semester 2
     */
    public function hitungTotalJpTpSem2(): int
    {
        $items = $this->master_tp ?? [];

        return (int) collect($items)
            ->filter(fn ($tp) => (int) ($tp['semester'] ?? 1) === 2)
            ->sum(fn ($tp) => (int) ($tp['alokasi_jp'] ?? 0));
    }

    /**
     * Hitung total JP dari seluruh TP Setahun
     */
    public function hitungTotalJpTp(): int
    {
        $items = $this->master_tp ?? [];

        return (int) collect($items)->sum(fn ($tp) => (int) ($tp['alokasi_jp'] ?? 0));
    }

    /**
     * Hitung total JP dari matriks distribusi mingguan PROSEM Semester 1
     */
    public function hitungTotalJpProsemSem1(): int
    {
        $items = $this->master_tp ?? [];
        $total = 0;

        foreach ($items as $tp) {
            if ((int) ($tp['semester'] ?? 1) !== 1) {
                continue;
            }
            $jadwal = $tp['jadwal_prosem'] ?? [];
            foreach ($jadwal as $bulan => $mingguArr) {
                if (is_array($mingguArr)) {
                    $total += array_sum($mingguArr);
                }
            }
        }

        return $total;
    }

    /**
     * Hitung total JP dari matriks distribusi mingguan PROSEM Semester 2
     */
    public function hitungTotalJpProsemSem2(): int
    {
        $items = $this->master_tp ?? [];
        $total = 0;

        foreach ($items as $tp) {
            if ((int) ($tp['semester'] ?? 1) !== 2) {
                continue;
            }
            $jadwal = $tp['jadwal_prosem'] ?? [];
            foreach ($jadwal as $bulan => $mingguArr) {
                if (is_array($mingguArr)) {
                    $total += array_sum($mingguArr);
                }
            }
        }

        return $total;
    }

    /**
     * Mengecek apakah JP AME, ATP, PROTA, dan PROSEM sinkron secara matematis
     */
    public function isJpSinkron(): bool
    {
        $jpAmeSem1 = $this->hitungTotalJpAmeSem1();
        $jpAmeSem2 = $this->hitungTotalJpAmeSem2();
        $jpTpSem1 = $this->hitungTotalJpTpSem1();
        $jpTpSem2 = $this->hitungTotalJpTpSem2();

        if ($jpAmeSem1 === 0 && $jpTpSem1 === 0) {
            return false;
        }

        return ($jpAmeSem1 === $jpTpSem1) && ($jpAmeSem2 === $jpTpSem2);
    }

    /**
     * Jumlah TP dalam master_tp
     */
    public function jumlahTp(): int
    {
        return count($this->master_tp ?? []);
    }

    /**
     * Jumlah modul ajar yang sudah di-generate
     */
    public function jumlahModulTersedia(): int
    {
        return count($this->modul_ajars ?? []);
    }

    /**
     * Ambil modul ajar berdasarkan kode_tp
     */
    public function getModulAjar(string $kodeTp): ?array
    {
        $modulMap = $this->modul_ajars ?? [];

        return $modulMap[$kodeTp] ?? null;
    }
}
