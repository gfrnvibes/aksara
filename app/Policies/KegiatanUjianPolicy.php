<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\KegiatanUjian;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class KegiatanUjianPolicy
{
    use HandlesAuthorization;

    public function before(AuthUser $authUser, string $ability): ?bool
    {
        if ($authUser instanceof User && $authUser->hasRole(config('filament-shield.super_admin.name'))) {
            return true;
        }

        return null;
    }

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
