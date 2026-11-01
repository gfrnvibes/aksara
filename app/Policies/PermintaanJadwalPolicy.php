<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PermintaanJadwal;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PermintaanJadwalPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PermintaanJadwal');
    }

    public function view(AuthUser $authUser, PermintaanJadwal $permintaanJadwal): bool
    {
        return $authUser->can('View:PermintaanJadwal');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PermintaanJadwal');
    }

    public function update(AuthUser $authUser, PermintaanJadwal $permintaanJadwal): bool
    {
        return $authUser->can('Update:PermintaanJadwal');
    }

    public function delete(AuthUser $authUser, PermintaanJadwal $permintaanJadwal): bool
    {
        return $authUser->can('Delete:PermintaanJadwal');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PermintaanJadwal');
    }
}
