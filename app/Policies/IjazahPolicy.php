<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Ijazah;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class IjazahPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Ijazah');
    }

    public function view(AuthUser $authUser, Ijazah $ijazah): bool
    {
        return $authUser->can('View:Ijazah');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Ijazah');
    }

    public function update(AuthUser $authUser, Ijazah $ijazah): bool
    {
        return $authUser->can('Update:Ijazah');
    }

    public function delete(AuthUser $authUser, Ijazah $ijazah): bool
    {
        return $authUser->can('Delete:Ijazah');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Ijazah');
    }
}
