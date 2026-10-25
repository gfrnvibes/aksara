<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MataPelajaran;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class MataPelajaranPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MataPelajaran');
    }

    public function view(AuthUser $authUser, MataPelajaran $mataPelajaran): bool
    {
        return $authUser->can('View:MataPelajaran');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MataPelajaran');
    }

    public function update(AuthUser $authUser, MataPelajaran $mataPelajaran): bool
    {
        return $authUser->can('Update:MataPelajaran');
    }

    public function delete(AuthUser $authUser, MataPelajaran $mataPelajaran): bool
    {
        return $authUser->can('Delete:MataPelajaran');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:MataPelajaran');
    }
}
