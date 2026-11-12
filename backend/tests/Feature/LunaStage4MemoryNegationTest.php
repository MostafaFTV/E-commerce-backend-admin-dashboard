<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ShoppingAssistant\ShoppingAssistantConsultationMemory as Memory;
use Tests\TestCase;

final class LunaStage4MemoryNegationTest extends TestCase
{
    public function test_rejected_coffee_does_not_override_selected_tea(): void
    {
        $state = Memory::observe([], 'نه، قهوه نمی‌خوام، چای می‌خوام', 'fa');
        $this->assertSame('tea', $state['preferences']['drink_type'] ?? null);
        $old = Memory::observe([], 'قهوه می‌خوام', 'fa');
        $new = Memory::observe($old, 'نه، چای', 'fa');
        $this->assertSame('tea', $new['preferences']['drink_type'] ?? null);
        $other = Memory::observe([], 'چای میخوام نه قهوه', 'fa');
        $this->assertSame('tea', $other['preferences']['drink_type'] ?? null);
    }

    public function test_english_contraction_retracts_compact_preference(): void
    {
        $old = Memory::observe([], 'I need a compact kettle', 'en');
        $this->assertSame('compact', $old['preferences']['product_size'] ?? null);
        $new = Memory::observe($old, "I don't want compact anymore", 'en');
        $this->assertArrayNotHasKey('product_size', $new['preferences']);
        $smart = Memory::observe($old, "I don’t want compact anymore", 'en');
        $this->assertArrayNotHasKey('product_size', $smart['preferences']);
    }

    public function test_negated_induction_is_not_recorded_as_affirmative(): void
    {
        $state = Memory::observe([], 'اجاق القایی نمی‌خوام', 'fa');
        $this->assertNotSame('induction', $state['preferences']['hob_type'] ?? null);
        $gas = Memory::observe([], 'اجاق گازی دارم', 'fa');
        $new = Memory::observe($gas, 'الان القاییه نه گازی', 'fa');
        $this->assertSame('induction', $new['preferences']['hob_type'] ?? null);
    }

    public function test_does_not_create_product_evidence_or_price_from_preferences(): void
    {
        $state = Memory::observe([], 'نه، قهوه نمی‌خوام، چای می‌خوام', 'fa');
        $this->assertArrayNotHasKey('products', $state);
        $this->assertArrayNotHasKey('price', $state['preferences']);
    }
}
