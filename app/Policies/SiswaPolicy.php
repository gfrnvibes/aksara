<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Siswa;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SiswaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Siswa');
    }

    public function view(AuthUser $authUser, Siswa $siswa): bool
    {
        return $authUser->can('View:Siswa');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Siswa');
    }

    public function update(AuthUser $authUser, Siswa $siswa): bool
    {
        return $authUser->can('Update:Siswa');
    }

    public function delete(AuthUser $authUser, Siswa $siswa): bool
    {
        return $authUser->can('Delete:Siswa');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Siswa');
    }
}
