<?php

namespace App\Http\Requests\Concerns;

/** Only admins choose a record's owner; an agent's choice is silently ignored. */
trait ResolvesOwner
{
    /** The owner an admin picked in $field, or null (not an admin, or left blank). */
    public function ownerIdFor(string $field = 'owner_id'): ?int
    {
        return $this->user()->isAdmin() && $this->filled($field) ? $this->integer($field) : null;
    }
}
