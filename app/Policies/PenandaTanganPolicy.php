<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PenandaTangan;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PenandaTanganPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PenandaTangan');
    }

    public function view(AuthUser $authUser, PenandaTangan $penandaTangan): bool
    {
        return $authUser->can('View:PenandaTangan');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PenandaTangan');
    }

    public function update(AuthUser $authUser, PenandaTangan $penandaTangan): bool
    {
        return $authUser->can('Update:PenandaTangan');
    }

    public function delete(AuthUser $authUser, PenandaTangan $penandaTangan): bool
    {
        return $authUser->can('Delete:PenandaTangan');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PenandaTangan');
    }
}
