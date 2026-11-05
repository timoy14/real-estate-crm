<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum DealStatus: string
{
    use HasLabel;

    case Open = 'open';
    case Won = 'won';
    case Lost = 'lost';
}
