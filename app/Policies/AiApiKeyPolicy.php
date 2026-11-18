<?php

namespace App\Policies;

use App\Models\AiApiKey;
use App\Models\User;

class AiApiKeyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:AiApiKey');
    }

    public function view(User $user, AiApiKey $aiApiKey): bool
    {
        return $user->can('View:AiApiKey');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:AiApiKey');
    }

    public function update(User $user, AiApiKey $aiApiKey): bool
    {
        return $user->can('Update:AiApiKey');
    }

    public function delete(User $user, AiApiKey $aiApiKey): bool
    {
        return $user->can('Delete:AiApiKey');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:AiApiKey');
    }
}
