<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\KalenderAkademik;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class KalenderAkademikPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:KalenderAkademik');
    }

    public function view(AuthUser $authUser, KalenderAkademik $kalenderAkademik): bool
    {
        return $authUser->can('View:KalenderAkademik');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:KalenderAkademik');
    }

    public function update(AuthUser $authUser, KalenderAkademik $kalenderAkademik): bool
    {
        return $authUser->can('Update:KalenderAkademik');
    }

    public function delete(AuthUser $authUser, KalenderAkademik $kalenderAkademik): bool
    {
        return $authUser->can('Delete:KalenderAkademik');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:KalenderAkademik');
    }
}
