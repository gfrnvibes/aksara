<?php

namespace App\Models\Concerns;

use App\Models\Contracts\TerlindungDariHapus;

/**
 * Cancels deleting a {@see TerlindungDariHapus} model that is still in use.
 *
 * The database would refuse the delete anyway (restrictOnDelete); checking first lets the admin panel
 * show why instead of failing with a server error. Retire the record (is_aktif) instead.
 */
trait MenolakHapusSaatDipakai
{
    public static function bootMenolakHapusSaatDipakai(): void
    {
        static::deleting(fn (TerlindungDariHapus $model): bool => $model->keteranganPemakaian() === null);
    }
}
