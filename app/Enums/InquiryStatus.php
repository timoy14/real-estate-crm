<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum InquiryStatus: string
{
    use HasLabel;

    case New = 'new';
    case Contacted = 'contacted';
    case Converted = 'converted';
    case Spam = 'spam';
}
