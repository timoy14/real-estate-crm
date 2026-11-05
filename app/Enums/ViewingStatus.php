<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum ViewingStatus: string
{
    use HasLabel;

    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
