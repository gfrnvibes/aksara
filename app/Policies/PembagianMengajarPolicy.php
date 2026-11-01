<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PembagianMengajar;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PembagianMengajarPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PembagianMengajar');
    }

    public function view(AuthUser $authUser, PembagianMengajar $pembagianMengajar): bool
    {
        return $authUser->can('View:PembagianMengajar');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PembagianMengajar');
    }

    public function update(AuthUser $authUser, PembagianMengajar $pembagianMengajar): bool
    {
        return $authUser->can('Update:PembagianMengajar');
    }

    public function delete(AuthUser $authUser, PembagianMengajar $pembagianMengajar): bool
    {
        return $authUser->can('Delete:PembagianMengajar');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PembagianMengajar');
    }
}
