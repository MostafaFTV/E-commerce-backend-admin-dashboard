<?php

namespace App\Enums;

enum CheckoutSessionStatus: string
{
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
}