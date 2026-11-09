<?php

namespace App\Actions;

use LogicException;
use Spatie\Activitylog\Actions\CleanActivityLogAction;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Support\Config;

class BersihkanActivityLog extends CleanActivityLogAction
{
    protected function deleteOldActivities(string $cutOffDate, ?string $logName): int
    {
        $model = Config::activityModelInstance();
        if (! $model instanceof Activity) {
            throw new LogicException('Model activity log harus mewarisi Spatie Activity.');
        }
        $deleted = 0;

        do {
            $ids = $model->newQuery()
                ->where('created_at', '<', $cutOffDate)
                ->when($logName !== null, fn ($query) => $query->inLog($logName))
                ->orderBy('created_at')->orderBy('id')
                ->limit(1000)->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += $model->newQuery()->whereKey($ids)->delete();
        } while ($ids->count() === 1000);

        return $deleted;
    }
}
