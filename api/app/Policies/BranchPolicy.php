<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('branches.manage') || $user->can('appointments.handle');
    }

    public function view(User $user, Branch $branch): bool
    {
        return $this->assignedOrOwner($user, $branch);
    }

    public function create(User $user): bool
    {
        return $user->can('branches.manage');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->can('branches.manage') && $this->assignedOrOwner($user, $branch);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $this->update($user, $branch);
    }

    private function assignedOrOwner(User $user, Branch $branch): bool
    {
        if ($user->hasRole('owner')) {
            return true;
        }

        if (! $user->can('branches.manage') && ! $user->can('appointments.handle')) {
            return false;
        }

        return $branch->users()->whereKey($user->id)->exists();
    }
}
