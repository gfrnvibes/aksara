<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SuratMasuk;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SuratMasukPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SuratMasuk');
    }

    public function view(AuthUser $authUser, SuratMasuk $suratMasuk): bool
    {
        return $authUser->can('View:SuratMasuk');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SuratMasuk');
    }

    public function update(AuthUser $authUser, SuratMasuk $suratMasuk): bool
    {
        return $authUser->can('Update:SuratMasuk');
    }

    public function delete(AuthUser $authUser, SuratMasuk $suratMasuk): bool
    {
        return $authUser->can('Delete:SuratMasuk');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SuratMasuk');
    }
}
