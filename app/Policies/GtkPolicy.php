<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Gtk;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class GtkPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Gtk');
    }

    public function view(AuthUser $authUser, Gtk $gtk): bool
    {
        return $authUser->can('View:Gtk');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Gtk');
    }

    public function update(AuthUser $authUser, Gtk $gtk): bool
    {
        return $authUser->can('Update:Gtk');
    }

    public function delete(AuthUser $authUser, Gtk $gtk): bool
    {
        return $authUser->can('Delete:Gtk');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Gtk');
    }
}
