<?php

namespace App\Enums;

enum CartStatus: string
{
    case ACTIVE = 'active';
    case CONVERTED = 'converted';
    case ABANDONED = 'abandoned';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::CONVERTED => 'Converted',
            self::ABANDONED => 'Abandoned',
        };
    }
}