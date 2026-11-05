<?php

namespace App\Enums;

enum ContactSource: string
{
    case Portal = 'portal';
    case Referral = 'referral';
    case WalkIn = 'walk_in';
    case Website = 'website';
    case Social = 'social';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Portal => 'Portal',
            self::Referral => 'Referral',
            self::WalkIn => 'Walk in',
            self::Website => 'Website',
            self::Social => 'Social',
            self::Other => 'Other',
        };
    }
}
