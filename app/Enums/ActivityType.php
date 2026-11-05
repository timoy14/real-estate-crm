<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum ActivityType: string
{
    use HasLabel;

    case Call = 'call';
    case Email = 'email';
    case Meeting = 'meeting';
    case Viewing = 'viewing';
    case Note = 'note';
}
