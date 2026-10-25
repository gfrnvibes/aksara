<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Records create/update/delete of the model in the activity log, keeping only the columns that changed.
 *
 * Hidden attributes (password, tokens) and timestamps are never written to the log.
 */
trait CatatAktivitas
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['created_at', 'updated_at', ...$this->getHidden()])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
