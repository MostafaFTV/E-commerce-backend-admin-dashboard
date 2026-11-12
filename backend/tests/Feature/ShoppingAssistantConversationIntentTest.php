<?php

namespace Tests\Feature;

use App\Services\ShoppingAssistant\ShoppingAssistantConversationIntent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ShoppingAssistantConversationIntentTest extends TestCase
{
    #[DataProvider('linkMessages')]
    public function test_detects_link_intent(string $message): void
    {
        self::assertTrue(ShoppingAssistantConversationIntent::wantsProductLink($message));
    }

    public static function linkMessages(): array
    {
        return [
            ['send me its link'], ['can I get the product URL?'],
            ['لینک این محصول رو میدی؟'], ['لینکش رو بده'],
        ];
    }

    public function test_detects_option_references(): void
    {
        self::assertSame(1, ShoppingAssistantConversationIntent::selectedIndex('link to the second option'));
        self::assertSame(1, ShoppingAssistantConversationIntent::selectedIndex('لینک دومی رو بده'));
        self::assertNull(ShoppingAssistantConversationIntent::selectedIndex('link to the product'));
    }
}
