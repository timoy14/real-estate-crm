<?php

namespace App\Enums;

enum PropertyType: string
{
    case House = 'house';
    case Apartment = 'apartment';
    case Condo = 'condo';
    case Land = 'land';
    case Commercial = 'commercial';

    public function label(): string
    {
        return match ($this) {
            self::House => 'House',
            self::Apartment => 'Apartment',
            self::Condo => 'Condo',
            self::Land => 'Land',
            self::Commercial => 'Commercial',
        };
    }
}
