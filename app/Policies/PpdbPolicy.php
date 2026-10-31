<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Ppdb;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PpdbPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Ppdb');
    }

    public function view(AuthUser $authUser, Ppdb $ppdb): bool
    {
        return $authUser->can('View:Ppdb');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Ppdb');
    }

    public function update(AuthUser $authUser, Ppdb $ppdb): bool
    {
        return $authUser->can('Update:Ppdb');
    }

    public function delete(AuthUser $authUser, Ppdb $ppdb): bool
    {
        return $authUser->can('Delete:Ppdb');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Ppdb');
    }
}
