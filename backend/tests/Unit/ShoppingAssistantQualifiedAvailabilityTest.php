<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ShoppingAssistant\ShoppingAssistantService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class ShoppingAssistantQualifiedAvailabilityTest extends TestCase
{
    private function contradicts(string $text, array $cards = []): bool
    {
        $instance = (new ReflectionClass(ShoppingAssistantService::class))->newInstanceWithoutConstructor();
        return (new ReflectionMethod(ShoppingAssistantService::class, 'contradictsCatalogAvailability'))
            ->invoke($instance, $text, $cards);
    }

    public function test_specific_uncertainty_is_not_an_absolute_denial(): void
    {
        self::assertFalse($this->contradicts(
            'سرویس زویلینگ موشن و سیمپلیفای در کاتالوگ هستند؛ موجودی هر دو نیاز به تأیید دارد.'
        ));
    }

    public function test_absolute_false_stock_claim_stays_blocked(): void
    {
        self::assertTrue($this->contradicts('همه سرویس‌های قابلمه ناموجود هستند.'));
    }

    public function test_an_in_stock_card_overrides_unverified_denial(): void
    {
        self::assertTrue($this->contradicts(
            'در اطلاعات موجود، گزینه موجودی ثبت نشده است.',
            [['id'=>1, 'availability'=>'in_stock']]
        ));
    }

    public function test_availability_uncertainty_does_not_override_verified_stock(): void
    {
        // The new exception must not bypass established verified-stock logic.
        self::assertTrue($this->contradicts(
            'در اطلاعات موجود، گزینه موجودی ثبت نشده است؛ موجودی هر دو نیاز به تأیید دارد.',
            [['id'=>1, 'availability'=>'in_stock']]
        ));
    }
}