<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StatusJadwal;
use App\Models\Jadwal;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class JadwalPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Jadwal');
    }

    /**
     * Teachers may open and print a published timetable from the teacher panel.
     */
    public function view(AuthUser $authUser, Jadwal $jadwal): bool
    {
        return $authUser->can('View:Jadwal')
            || ($jadwal->status === StatusJadwal::Terbit && $authUser instanceof User && $authUser->gtk()->aktif()->exists());
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Jadwal');
    }

    public function update(AuthUser $authUser, Jadwal $jadwal): bool
    {
        return $authUser->can('Update:Jadwal');
    }

    public function delete(AuthUser $authUser, Jadwal $jadwal): bool
    {
        return $authUser->can('Delete:Jadwal');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Jadwal');
    }
}
