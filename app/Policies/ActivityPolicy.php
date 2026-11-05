<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;
use App\Policies\Concerns\OwnsRecord;

class ActivityPolicy
{
    use OwnsRecord;

    public function viewAny(User $user): bool
    {
        return true;
    }

    /** Same rule as Activity::scopeVisibleTo. */
    public function view(User $user, Activity $activity): bool
    {
        return $user->isAdmin()
            || $activity->owner_id === $user->id
            || $activity->contact?->owner_id === $user->id
            || $activity->deal?->owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Activity $activity): bool
    {
        return $this->owns($user, $activity);
    }

    public function delete(User $user, Activity $activity): bool
    {
        return $this->update($user, $activity);
    }
}
