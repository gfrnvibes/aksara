<?php

namespace App\Policies;

use App\Models\KopSurat;
use App\Models\User;

class KopSuratPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:KopSurat');
    }

    public function view(User $user, KopSurat $kop): bool
    {
        return $user->can('View:KopSurat');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:KopSurat');
    }

    public function update(User $user, KopSurat $kop): bool
    {
        return $user->can('Update:KopSurat');
    }

    public function delete(User $user, KopSurat $kop): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
