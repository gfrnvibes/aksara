<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MutasiSiswa;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class MutasiSiswaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MutasiSiswa');
    }

    public function view(AuthUser $authUser, MutasiSiswa $mutasiSiswa): bool
    {
        return $authUser->can('View:MutasiSiswa');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MutasiSiswa');
    }

    public function update(AuthUser $authUser, MutasiSiswa $mutasiSiswa): bool
    {
        return $authUser->can('Update:MutasiSiswa');
    }

    public function delete(AuthUser $authUser, MutasiSiswa $mutasiSiswa): bool
    {
        return $authUser->can('Delete:MutasiSiswa');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:MutasiSiswa');
    }
}
