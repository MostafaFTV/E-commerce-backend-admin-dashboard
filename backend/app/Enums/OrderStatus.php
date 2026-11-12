<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING_PAYMENT = 'pending_payment';
    case PAID = 'paid';
    case PROCESSING = 'processing';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';
    case RETURNED = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_PAYMENT => 'Pending Payment',
            self::PAID => 'Paid',
            self::PROCESSING => 'Processing',
            self::SHIPPED => 'Shipped',
            self::DELIVERED => 'Delivered',
            self::CANCELLED => 'Cancelled',
            self::RETURNED => 'Returned',
        };
    }

    /**
     * Statuses that can normally follow the current status.
     *
     * Note:
     * Customer-facing authorization is handled separately
     * through Policies / Services / Permissions.
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PENDING_PAYMENT => [
                self::PAID,
                self::CANCELLED,
            ],

            self::PAID => [
                self::PROCESSING,
            ],

            self::PROCESSING => [
                self::SHIPPED,
                self::CANCELLED,
            ],

            self::SHIPPED => [
                self::DELIVERED,
                self::RETURNED,
            ],

            self::DELIVERED => [
                self::RETURNED,
            ],

            self::CANCELLED => [],

            self::RETURNED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array(
            $target,
            $this->allowedTransitions(),
            true
        );
    }

    public function isTerminal(): bool
    {
        return in_array(
            $this,
            [
                self::CANCELLED,
                self::RETURNED,
            ],
            true
        );
    }
}