<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\GaleriKegiatan;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class GaleriKegiatanPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:GaleriKegiatan');
    }

    public function view(AuthUser $authUser, GaleriKegiatan $galeriKegiatan): bool
    {
        return $authUser->can('View:GaleriKegiatan');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:GaleriKegiatan');
    }

    public function update(AuthUser $authUser, GaleriKegiatan $galeriKegiatan): bool
    {
        return $authUser->can('Update:GaleriKegiatan');
    }

    public function delete(AuthUser $authUser, GaleriKegiatan $galeriKegiatan): bool
    {
        return $authUser->can('Delete:GaleriKegiatan');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:GaleriKegiatan');
    }
}
