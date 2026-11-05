<?php

namespace App\Support;

use App\Models\User;

class Agents
{
    /** The `agents` prop (owner pickers) exists for admins only. */
    public static function prop(User $user): array
    {
        return $user->isAdmin()
            ? ['agents' => User::query()->orderBy('name')->get(['id', 'name'])]
            : [];
    }
}
