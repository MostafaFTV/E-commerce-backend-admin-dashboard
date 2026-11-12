<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ShoppingAssistant\ShoppingAssistantConsultationMemory as Memory;
use App\Services\ShoppingAssistant\ShoppingAssistantConsultationPlanner as Planner;
use Tests\TestCase;

final class LunaStage4IntegratedFindingRegressionTest extends TestCase
{
    public function test_explicit_induction_retraction_clears_previous_hob_without_guessing(): void
    {
        $state = Memory::observe([], 'برای اجاق گازی قابلمه می‌خوام', 'fa');
        self::assertSame('gas', $state['preferences']['hob_type'] ?? null);
        $state = Memory::observe($state, 'الان القاییه نه گازی', 'fa');
        self::assertSame('induction', $state['preferences']['hob_type'] ?? null);
        $state = Memory::observe($state, 'نه، القایی نمیخوام', 'fa');
        self::assertArrayNotHasKey('hob_type', $state['preferences']);
    }

    public function test_induction_retraction_does_not_overwrite_explicit_replacement(): void
    {
        $state = Memory::observe([], 'اجاق القایی دارم', 'fa');
        self::assertSame('induction', $state['preferences']['hob_type'] ?? null);
        $state = Memory::observe($state, 'القایی نمیخوام، اجاق گازی میخوام', 'fa');
        self::assertSame('gas', $state['preferences']['hob_type'] ?? null);
    }

    public function test_false_induction_negation_cannot_create_induction(): void
    {
        $state = Memory::observe([], 'نه، القایی نمیخوام', 'fa');
        self::assertArrayNotHasKey('hob_type', $state['preferences']);
    }

    public function test_existing_drink_correction_remains_intact(): void
    {
        $state = Memory::observe([], 'قهوه میخوام', 'fa');
        $state = Memory::observe($state, 'نه قهوه نمیخوام، چای میخوام', 'fa');
        self::assertSame('tea', $state['preferences']['drink_type'] ?? null);
    }

    public function test_english_which_one_decision_is_detected(): void
    {
        $context = Planner::context(['stage' => 'clarify', 'preferences' => ['product_size' => 'compact']], 'Which one would you recommend?');
        self::assertTrue($context['decision_requested']);
        self::assertSame('decide', $context['stage']);
        self::assertSame('compact', $context['explicit_customer_preferences']['product_size']);
    }

    public function test_old_decision_forms_remain_detected(): void
    {
        foreach (['Which would you recommend?', 'What would you choose?', 'کدوم بهتره؟', 'کدوم رو پیشنهاد می‌کنی؟'] as $message) {
            self::assertTrue(Planner::context(['stage' => 'clarify'], $message)['decision_requested'], $message);
        }
    }

    public function test_warranty_verification_question_is_not_a_buying_decision(): void
    {
        $context = Planner::context(['stage' => 'clarify'], 'Does this model have a lifetime warranty?');
        self::assertFalse($context['decision_requested']);
    }
}
