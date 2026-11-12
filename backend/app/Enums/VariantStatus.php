<?php

namespace App\Enums;

enum VariantStatus: string
{
    case ACTIVE = 'active';

    case MADE_TO_ORDER = 'made_to_order';

    case SOLD = 'sold';

    case INACTIVE = 'inactive';


    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::MADE_TO_ORDER => 'Made to Order',
            self::SOLD => 'Sold',
            self::INACTIVE => 'Inactive',
        };
    }


    public function isPurchasable(): bool
    {
        return match ($this) {
            self::ACTIVE,
            self::MADE_TO_ORDER => true,

            self::SOLD,
            self::INACTIVE => false,
        };
    }


    public function requiresInventory(): bool
    {
        return $this === self::ACTIVE;
    }
}
