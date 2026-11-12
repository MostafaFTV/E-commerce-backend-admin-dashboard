<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ShoppingAssistant\ShoppingAssistantContextualFollowUp;
use PHPUnit\Framework\TestCase;

final class ShoppingAssistantHouseholdContinuationTest extends TestCase
{
    public function test_household_size_continues_purchase_without_question(): void
    {
        self::assertTrue(ShoppingAssistantContextualFollowUp::isContinuation(
            'Ø®Ø§Ù†ÙˆØ§Ø¯Ù‡ Ù…Ø§ Ú†Ù‡Ø§Ø± Ù†ÙØ±Ù‡ Ø§Ø³Øª.',
            [
                ['role' => 'user', 'content' => 'Ø¨Ø±Ø§ÛŒ Ø¢Ø´Ù¾Ø²ÛŒ Ø±ÙˆØ²Ù…Ø±Ù‡ ÛŒÙ‡ Ø³Ø±ÙˆÛŒØ³ Ù‚Ø§Ø¨Ù„Ù…Ù‡ Ø®ÙˆØ¨ Ù…ÛŒâ€ŒØ®ÙˆØ§Ù….'],
                ['role' => 'assistant', 'content' => 'Ø§ÙˆÙ„ÙˆÛŒØªâ€ŒÙ‡Ø§ Ø±Ø§ Ø¨Ø±Ø±Ø³ÛŒ Ù…ÛŒâ€ŒÚ©Ù†Ù….'],
            ],
            []
        ));
    }

    public function test_unrelated_conversation_does_not_continue(): void
    {
        self::assertFalse(ShoppingAssistantContextualFollowUp::isContinuation(
            'Ø®Ø§Ù†ÙˆØ§Ø¯Ù‡ Ù…Ø§ Ú†Ù‡Ø§Ø± Ù†ÙØ±Ù‡ Ø§Ø³Øª.',
            [
                ['role' => 'user', 'content' => 'Ø§Ù…Ø±ÙˆØ² Ù‡ÙˆØ§ Ú†Ø·ÙˆØ±Ù‡ØŸ'],
                ['role' => 'assistant', 'content' => 'Ø¯Ø±Ø¨Ø§Ø±Ù‡ Ù…Ø­ØµÙˆÙ„Ø§Øª Ø¢Ø´Ù¾Ø²Ø®Ø§Ù†Ù‡ Ù…ÛŒâ€ŒØªÙˆØ§Ù†Ù… Ú©Ù…Ú© Ú©Ù†Ù….'],
            ],
            []
        ));
    }

    public function test_latest_user_message_controls_context(): void
    {
        self::assertFalse(ShoppingAssistantContextualFollowUp::isContinuation(
            'Ø®Ø§Ù†ÙˆØ§Ø¯Ù‡ Ù…Ø§ Ú†Ù‡Ø§Ø± Ù†ÙØ±Ù‡ Ø§Ø³Øª.',
            [
                ['role' => 'user', 'content' => 'ÛŒÙ‡ Ù‚Ø§Ø¨Ù„Ù…Ù‡ Ù…ÛŒâ€ŒØ®ÙˆØ§Ù…'],
                ['role' => 'assistant', 'content' => 'Ù…ØªÙˆØ¬Ù‡ Ø´Ø¯Ù….'],
                ['role' => 'user', 'content' => 'Ø¯Ø±Ø¨Ø§Ø±Ù‡ ÙÛŒÙ„Ù… Ø­Ø±Ù Ø¨Ø²Ù†'],
                ['role' => 'assistant', 'content' => 'Ù…ÙˆØ¶ÙˆØ¹ Ø±Ø§ Ø¹ÙˆØ¶ Ú©Ù†ÛŒÙ….'],
            ],
            []
        ));
    }
}