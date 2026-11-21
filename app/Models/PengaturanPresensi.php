<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property bool $aktif
 * @property string $nama_lokasi
 * @property float|null $latitude
 * @property float|null $longitude
 * @property int $radius_meter
 * @property string $jam_mulai
 * @property string $jam_selesai
 * @property bool $wajib_foto
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'aktif',
    'nama_lokasi',
    'latitude',
    'longitude',
    'radius_meter',
    'jam_mulai',
    'jam_selesai',
    'wajib_foto',
])]
class PengaturanPresensi extends Model
{
    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_meter' => 'integer',
            'wajib_foto' => 'boolean',
        ];
    }

    public static function aktif(): self
    {
        return static::query()->firstOrCreate([], [
            'aktif' => true,
            'nama_lokasi' => 'Madrasah',
            'radius_meter' => 100,
            'jam_mulai' => '06:00',
            'jam_selesai' => '08:30',
            'wajib_foto' => true,
        ]);
    }
}
