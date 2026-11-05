<?php

namespace App\Enums;

enum PropertyStatus: string
{
    case Draft = 'draft';
    case Available = 'available';
    case UnderOffer = 'under_offer';
    case Sold = 'sold';
    case Rented = 'rented';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Available => 'Available',
            self::UnderOffer => 'Under offer',
            self::Sold => 'Sold',
            self::Rented => 'Rented',
            self::Withdrawn => 'Withdrawn',
        };
    }
}
