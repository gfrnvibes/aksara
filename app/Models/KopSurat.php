<?php

namespace App\Models;

use App\Models\Concerns\CatatAktivitas;
use App\Services\Surat\PengaturanKop;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $nama
 * @property array<string, mixed> $konfigurasi
 */
#[Fillable(['nama', 'konfigurasi'])]
class KopSurat extends Model
{
    use CatatAktivitas;

    protected function casts(): array
    {
        return ['konfigurasi' => 'array'];
    }

    protected static function booted(): void
    {
        static::saving(function (KopSurat $kop): void {
            $kop->konfigurasi = PengaturanKop::validasi($kop->konfigurasi);
        });
    }
}
