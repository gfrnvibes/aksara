<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\AkreditasiBukti;
use Illuminate\Auth\Access\HandlesAuthorization;

class AkreditasiBuktiPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AkreditasiBukti');
    }

    public function view(AuthUser $authUser, AkreditasiBukti $akreditasiBukti): bool
    {
        return $authUser->can('View:AkreditasiBukti');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AkreditasiBukti');
    }

    public function update(AuthUser $authUser, AkreditasiBukti $akreditasiBukti): bool
    {
        return $authUser->can('Update:AkreditasiBukti');
    }

    public function delete(AuthUser $authUser, AkreditasiBukti $akreditasiBukti): bool
    {
        return $authUser->can('Delete:AkreditasiBukti');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AkreditasiBukti');
    }

}