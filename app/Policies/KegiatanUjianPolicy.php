<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\KegiatanUjian;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class KegiatanUjianPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:KegiatanUjian');
    }

    public function view(AuthUser $authUser, KegiatanUjian $kegiatanUjian): bool
    {
        return $authUser->can('View:KegiatanUjian');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:KegiatanUjian');
    }

    public function update(AuthUser $authUser, KegiatanUjian $kegiatanUjian): bool
    {
        return $authUser->can('Update:KegiatanUjian');
    }

    public function delete(AuthUser $authUser, KegiatanUjian $kegiatanUjian): bool
    {
        return $authUser->can('Delete:KegiatanUjian');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:KegiatanUjian');
    }
}
