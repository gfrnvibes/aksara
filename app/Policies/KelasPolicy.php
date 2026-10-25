<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Kelas;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class KelasPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Kelas');
    }

    public function view(AuthUser $authUser, Kelas $kelas): bool
    {
        return $authUser->can('View:Kelas');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Kelas');
    }

    public function update(AuthUser $authUser, Kelas $kelas): bool
    {
        return $authUser->can('Update:Kelas');
    }

    public function delete(AuthUser $authUser, Kelas $kelas): bool
    {
        return $authUser->can('Delete:Kelas');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Kelas');
    }
}
