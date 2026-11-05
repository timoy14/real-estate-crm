<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum TaskPriority: string
{
    use HasLabel;

    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
}
