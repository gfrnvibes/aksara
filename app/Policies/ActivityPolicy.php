<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Spatie\Activitylog\Models\Activity;

/**
 * The activity log is an audit trail: it can be read with the Shield permissions, but never edited or deleted.
 */
class ActivityPolicy
{
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Activity');
    }

    public function view(AuthUser $authUser, Activity $activity): bool
    {
        return $authUser->can('View:Activity');
    }

    public function create(AuthUser $authUser): bool
    {
        return false;
    }

    public function update(AuthUser $authUser, Activity $activity): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, Activity $activity): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function restore(AuthUser $authUser, Activity $activity): bool
    {
        return false;
    }
}
