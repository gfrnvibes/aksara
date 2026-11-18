<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PelanggaranSiswa;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PelanggaranSiswaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PelanggaranSiswa');
    }

    public function view(AuthUser $authUser, PelanggaranSiswa $pelanggaranSiswa): bool
    {
        return $authUser->can('View:PelanggaranSiswa');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PelanggaranSiswa');
    }

    public function update(AuthUser $authUser, PelanggaranSiswa $pelanggaranSiswa): bool
    {
        return $authUser->can('Update:PelanggaranSiswa');
    }

    public function delete(AuthUser $authUser, PelanggaranSiswa $pelanggaranSiswa): bool
    {
        return $authUser->can('Delete:PelanggaranSiswa');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PelanggaranSiswa');
    }
}
