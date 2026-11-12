<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case PURCHASE = 'purchase';
    case SALE = 'sale';
    case RESERVATION = 'reservation';
    case RELEASE = 'release';
    case RETURN = 'return';
    case ADJUSTMENT = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::PURCHASE => 'Purchase',
            self::SALE => 'Sale',
            self::RESERVATION => 'Reservation',
            self::RELEASE => 'Release',
            self::RETURN => 'Return',
            self::ADJUSTMENT => 'Adjustment',
        };
    }
}