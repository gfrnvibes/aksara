<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\AkreditasiDokumen;
use Illuminate\Auth\Access\HandlesAuthorization;

class AkreditasiDokumenPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AkreditasiDokumen');
    }

    public function view(AuthUser $authUser, AkreditasiDokumen $akreditasiDokumen): bool
    {
        return $authUser->can('View:AkreditasiDokumen');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AkreditasiDokumen');
    }

    public function update(AuthUser $authUser, AkreditasiDokumen $akreditasiDokumen): bool
    {
        return $authUser->can('Update:AkreditasiDokumen');
    }

    public function delete(AuthUser $authUser, AkreditasiDokumen $akreditasiDokumen): bool
    {
        return $authUser->can('Delete:AkreditasiDokumen');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AkreditasiDokumen');
    }

}