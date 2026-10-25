<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TahunAjaran;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class TahunAjaranPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TahunAjaran');
    }

    public function view(AuthUser $authUser, TahunAjaran $tahunAjaran): bool
    {
        return $authUser->can('View:TahunAjaran');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TahunAjaran');
    }

    public function update(AuthUser $authUser, TahunAjaran $tahunAjaran): bool
    {
        return $authUser->can('Update:TahunAjaran');
    }

    public function delete(AuthUser $authUser, TahunAjaran $tahunAjaran): bool
    {
        return $authUser->can('Delete:TahunAjaran');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TahunAjaran');
    }
}
