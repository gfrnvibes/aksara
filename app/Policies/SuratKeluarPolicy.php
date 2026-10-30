<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SuratKeluar;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SuratKeluarPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SuratKeluar');
    }

    public function view(AuthUser $authUser, SuratKeluar $suratKeluar): bool
    {
        return $authUser->can('View:SuratKeluar');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SuratKeluar');
    }

    public function update(AuthUser $authUser, SuratKeluar $suratKeluar): bool
    {
        return $authUser->can('Update:SuratKeluar');
    }

    public function delete(AuthUser $authUser, SuratKeluar $suratKeluar): bool
    {
        return $authUser->can('Delete:SuratKeluar');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SuratKeluar');
    }
}
