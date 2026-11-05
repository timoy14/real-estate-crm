<?php

namespace App\Enums;

enum ContactStatus: string
{
    case NewLead = 'new_lead';
    case Active = 'active';
    case Closed = 'closed';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::NewLead => 'New lead',
            self::Active => 'Active',
            self::Closed => 'Closed',
            self::Lost => 'Lost',
        };
    }
}
