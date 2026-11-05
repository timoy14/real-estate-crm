<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait OwnsRecord
{
    /** Admins, or the user whose id is in $column. */
    protected function owns(User $user, Model $record, string $column = 'owner_id'): bool
    {
        return $user->isAdmin() || $record->{$column} === $user->id;
    }
}
