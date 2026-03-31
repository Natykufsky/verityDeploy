<?php

namespace App\Policies;

use App\Models\Deployment;
use App\Models\User;

class DeploymentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Deployment $deployment): bool
    {
        return $user->canAccessSite($deployment->site);
    }

    public function delete(User $user, Deployment $deployment): bool
    {
        return $user->canAccessSite($deployment->site);
    }
}
