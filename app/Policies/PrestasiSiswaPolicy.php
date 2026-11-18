<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PrestasiSiswa;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PrestasiSiswaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PrestasiSiswa');
    }

    public function view(AuthUser $authUser, PrestasiSiswa $prestasiSiswa): bool
    {
        return $authUser->can('View:PrestasiSiswa');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PrestasiSiswa');
    }

    public function update(AuthUser $authUser, PrestasiSiswa $prestasiSiswa): bool
    {
        return $authUser->can('Update:PrestasiSiswa');
    }

    public function delete(AuthUser $authUser, PrestasiSiswa $prestasiSiswa): bool
    {
        return $authUser->can('Delete:PrestasiSiswa');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PrestasiSiswa');
    }
}
