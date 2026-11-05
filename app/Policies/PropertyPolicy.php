<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;
use App\Policies\Concerns\OwnsRecord;

class PropertyPolicy
{
    use OwnsRecord;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Property $property): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Property $property): bool
    {
        return $this->manages($user, $property);
    }

    public function delete(User $user, Property $property): bool
    {
        return $this->manages($user, $property);
    }

    public function managePhotos(User $user, Property $property): bool
    {
        return $this->manages($user, $property);
    }

    public function viewOwnerDetails(User $user, Property $property): bool
    {
        return $this->manages($user, $property);
    }

    private function manages(User $user, Property $property): bool
    {
        return $this->owns($user, $property, 'listing_agent_id');
    }
}
