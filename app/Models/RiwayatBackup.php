<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $batch
 * @property string $disk
 * @property string $jenis
 * @property string $status
 * @property string $sumber
 * @property string|null $path
 * @property int|null $ukuran
 * @property string|null $kesalahan
 */
#[Guarded(['id'])]
class RiwayatBackup extends Model
{
    protected function casts(): array
    {
        return ['mulai_at' => 'datetime', 'selesai_at' => 'datetime', 'ukuran' => 'integer'];
    }
}
