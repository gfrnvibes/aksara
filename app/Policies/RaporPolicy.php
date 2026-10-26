<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Rapor;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class RaporPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Rapor');
    }

    public function view(AuthUser $authUser, Rapor $rapor): bool
    {
        return $authUser->can('View:Rapor');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Rapor');
    }

    public function update(AuthUser $authUser, Rapor $rapor): bool
    {
        return $authUser->can('Update:Rapor');
    }

    public function delete(AuthUser $authUser, Rapor $rapor): bool
    {
        return $authUser->can('Delete:Rapor');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Rapor');
    }

    public function restore(AuthUser $authUser, Rapor $rapor): bool
    {
        return $authUser->can('Restore:Rapor');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Rapor');
    }

    public function forceDelete(AuthUser $authUser, Rapor $rapor): bool
    {
        return $authUser->can('ForceDelete:Rapor');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Rapor');
    }
}
