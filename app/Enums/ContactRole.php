<?php

namespace App\Enums;

enum ContactRole: string
{
    case Buyer = 'buyer';
    case Seller = 'seller';
    case Tenant = 'tenant';
    case Landlord = 'landlord';

    public function label(): string
    {
        return match ($this) {
            self::Buyer => 'Buyer',
            self::Seller => 'Seller',
            self::Tenant => 'Tenant',
            self::Landlord => 'Landlord',
        };
    }
}
