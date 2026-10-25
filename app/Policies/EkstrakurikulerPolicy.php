<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Ekstrakurikuler;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class EkstrakurikulerPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Ekstrakurikuler');
    }

    public function view(AuthUser $authUser, Ekstrakurikuler $ekstrakurikuler): bool
    {
        return $authUser->can('View:Ekstrakurikuler');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Ekstrakurikuler');
    }

    public function update(AuthUser $authUser, Ekstrakurikuler $ekstrakurikuler): bool
    {
        return $authUser->can('Update:Ekstrakurikuler');
    }

    public function delete(AuthUser $authUser, Ekstrakurikuler $ekstrakurikuler): bool
    {
        return $authUser->can('Delete:Ekstrakurikuler');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Ekstrakurikuler');
    }
}
