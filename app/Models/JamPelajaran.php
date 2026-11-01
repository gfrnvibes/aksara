<?php

namespace App\Models;

use App\Enums\Hari;
use App\Enums\JenisJamPelajaran;
use App\Models\Concerns\CatatAktivitas;
use BokshornIt\FilamentActivityTimeline\Contracts\ProvidesActivityTitle;
use Database\Factories\JamPelajaranFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One row of a school day's bell schedule. Lesson periods are numbered (jp_ke) in time order within their day,
 * which is how timetables refer to them.
 *
 * @property int $id
 * @property Hari $hari
 * @property string $mulai "HH:MM:SS"
 * @property string $selesai "HH:MM:SS"
 * @property JenisJamPelajaran $jenis
 * @property int|null $jp_ke Set for lesson periods only.
 * @property string|null $keterangan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['hari', 'mulai', 'selesai', 'jenis', 'keterangan'])]
class JamPelajaran extends Model implements ProvidesActivityTitle
{
    use CatatAktivitas;

    /** @use HasFactory<JamPelajaranFactory> */
    use HasFactory;

    /**
     * The bell schedule of MTs At-Taufiq for the 2026/2027 odd semester, offered when none is set up yet.
     *
     * @var array<int, list<array{0: string, 1: string, 2: string, 3?: string}>>
     */
    public const array BAWAAN = [
        Hari::Senin->value => self::HARI_BIASA_8_JP,
        Hari::Selasa->value => self::HARI_BIASA_9_JP,
        Hari::Rabu->value => self::HARI_BIASA_9_JP,
        Hari::Kamis->value => self::HARI_BIASA_9_JP,
        Hari::Jumat->value => [
            ['07:00', '07:35', 'pelajaran'],
            ['07:35', '08:10', 'pelajaran'],
            ['08:10', '08:45', 'pelajaran'],
            ['08:45', '09:15', 'pelajaran'],
            ['09:15', '09:35', 'istirahat', 'Istirahat'],
            ['09:35', '10:10', 'pelajaran'],
        ],
        Hari::Sabtu->value => self::HARI_BIASA_8_JP,
    ];

    /**
     * @var list<array{0: string, 1: string, 2: string, 3?: string}>
     */
    protected const array HARI_BIASA_8_JP = [
        ['07:00', '07:30', 'kegiatan', 'Shalat Duha / Tadarus'],
        ['07:30', '08:05', 'pelajaran'],
        ['08:05', '08:40', 'pelajaran'],
        ['08:40', '09:15', 'pelajaran'],
        ['09:15', '09:50', 'pelajaran'],
        ['09:50', '10:10', 'istirahat', 'Istirahat'],
        ['10:10', '10:45', 'pelajaran'],
        ['10:45', '11:20', 'pelajaran'],
        ['11:20', '11:55', 'pelajaran'],
        ['11:55', '12:15', 'kegiatan', 'Shalat Dzuhur'],
        ['12:15', '12:50', 'pelajaran'],
    ];

    /**
     * @var list<array{0: string, 1: string, 2: string, 3?: string}>
     */
    protected const array HARI_BIASA_9_JP = [
        ...self::HARI_BIASA_8_JP,
        ['12:50', '13:25', 'pelajaran'],
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hari' => Hari::class,
            'jenis' => JenisJamPelajaran::class,
            'jp_ke' => 'integer',
        ];
    }

    /**
     * Renumber the day's lesson periods whenever one of its rows changes.
     */
    protected static function booted(): void
    {
        static::saved(function (JamPelajaran $jam): void {
            static::nomoriUlang($jam->hari);

            $hariLama = $jam->getPrevious()['hari'] ?? null;

            if ($hariLama !== null && (int) $hariLama !== $jam->hari->value) {
                static::nomoriUlang(Hari::from((int) $hariLama));
            }
        });

        static::deleted(fn (JamPelajaran $jam) => static::nomoriUlang($jam->hari));
    }

    /**
     * @return Attribute<string, string>
     */
    protected function mulai(): Attribute
    {
        return Attribute::set(fn (string $jam): string => static::normalkanJam($jam));
    }

    /**
     * @return Attribute<string, string>
     */
    protected function selesai(): Attribute
    {
        return Attribute::set(fn (string $jam): string => static::normalkanJam($jam));
    }

    /**
     * Store times as "HH:MM:SS" on every database, so they sort in time order.
     */
    protected static function normalkanJam(string $jam): string
    {
        return Carbon::parse($jam)->format('H:i:s');
    }

    /**
     * Number the lesson periods of the day 1, 2, 3… in time order, leaving breaks and activities unnumbered.
     */
    public static function nomoriUlang(Hari $hari): void
    {
        $nomor = 0;

        foreach (static::query()->where('hari', $hari)->orderBy('mulai')->get() as $jam) {
            $jpKe = $jam->jenis === JenisJamPelajaran::Pelajaran ? ++$nomor : null;

            if ($jam->jp_ke !== $jpKe) {
                $jam->jp_ke = $jpKe;
                $jam->saveQuietly();
            }
        }
    }

    /**
     * Replace the whole bell schedule with {@see BAWAAN}.
     */
    public static function isiBawaan(): void
    {
        DB::transaction(function (): void {
            static::query()->delete();

            foreach (static::BAWAAN as $hari => $baris) {
                foreach ($baris as $isian) {
                    static::query()->create([
                        'hari' => $hari,
                        'mulai' => $isian[0],
                        'selesai' => $isian[1],
                        'jenis' => $isian[2],
                        'keterangan' => $isian[3] ?? null,
                    ]);
                }
            }
        });
    }

    /**
     * "07:30 – 08:05".
     */
    public function rentang(): string
    {
        return substr($this->mulai, 0, 5).' – '.substr($this->selesai, 0, 5);
    }

    public function label(): string
    {
        return $this->jenis === JenisJamPelajaran::Pelajaran ? "JP {$this->jp_ke}" : (string) ($this->keterangan ?? $this->jenis->getLabel());
    }

    public function activityTitle(): ?string
    {
        return "{$this->hari->getLabel()} {$this->rentang()} ({$this->label()})";
    }
}
