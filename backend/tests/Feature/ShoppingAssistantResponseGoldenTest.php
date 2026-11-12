<?php

namespace Tests\Feature;

use App\Services\ShoppingAssistant\ShoppingAssistantResponseContract;
use Tests\TestCase;

final class ShoppingAssistantResponseGoldenTest extends TestCase
{
    public function test_plain_text_remains_backwards_compatible(): void
    {
        $this->assertNull(ShoppingAssistantResponseContract::parse('Here are some options.'));
    }

    public function test_valid_persian_response_and_followup_are_parsed(): void
    {
        $raw = json_encode([
            'response_version' => 2,
            'message' => 'برای آشپزخانه کوچک این گزینه‌ها مناسب‌اند.',
            'recommended_product_ids' => [15, 9],
            'next_question' => 'بیشتر چای می‌نوشید یا قهوه؟',
        ], JSON_UNESCAPED_UNICODE);
        $parsed = ShoppingAssistantResponseContract::parse($raw);
        $this->assertSame([15, 9], $parsed['recommended_product_ids']);
        $this->assertStringContainsString('قهوه؟', $parsed['content']);
    }

    public function test_invalid_json_ids_and_duplicates_are_rejected(): void
    {
        foreach ([[1, '2'], [3, 3], [0], [1, 2, 3, 4]] as $ids) {
            $raw = json_encode(['response_version' => 2, 'message' => 'Options', 'recommended_product_ids' => $ids]);
            $this->assertNull(ShoppingAssistantResponseContract::parse($raw));
        }
    }

    public function test_trusted_cards_use_explicit_id_order_and_not_generated_metadata(): void
    {
        $trusted = [
            ['id' => 8, 'name' => 'Eight', 'url' => '/products/eight'],
            ['id' => 3, 'name' => 'Three', 'url' => '/products/three'],
        ];
        $this->assertSame([$trusted[1], $trusted[0]], ShoppingAssistantResponseContract::verifiedCards([3, 8], $trusted));
        $this->assertSame([], ShoppingAssistantResponseContract::verifiedCards([3, 999], $trusted));
        $this->assertSame([], ShoppingAssistantResponseContract::verifiedCards([3, 3], $trusted));
    }

    public function test_invalid_or_missing_schema_stays_unstructured(): void
    {
        $this->assertNull(ShoppingAssistantResponseContract::parse('{"message":"Hello"}'));
        $this->assertNull(ShoppingAssistantResponseContract::parse('```json {"response_version":2} ```'));
        $this->assertNull(ShoppingAssistantResponseContract::parse('{"response_version":2,"message":"","recommended_product_ids":[]}'));
    }
}
