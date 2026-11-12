<?php

namespace Tests\Feature;

use App\Services\ShoppingAssistant\ShoppingAssistantConversationRouter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ShoppingAssistantConversationRouterTest extends TestCase
{
    public static function pages(): array
    {
        return [
            ['لینک تماس با ما رو بده', '/contact'],
            ['Open the about us page', '/about'],
            ['Where is the FAQ page?', '/faq'],
            ['لینک صفحه محصولات رو بده', '/products'],
            ['لینک صفحه کالکشن‌ها', '/collections'],
        ];
    }

    #[DataProvider('pages')]
    public function test_known_page_navigation(
        string $input,
        string $path
    ): void {
        $this->assertSame(
            $path,
            ShoppingAssistantConversationRouter::page($input)['path']
        );
    }

    public function test_product_explanation_is_not_about_page(): void
    {
        $this->assertNull(
            ShoppingAssistantConversationRouter::page(
                'Tell me more about the second one and send its link.'
            )
        );

        $this->assertSame(
            1,
            ShoppingAssistantConversationRouter::optionIndex(
                'Tell me more about the second one and send its link.'
            )
        );

        $this->assertSame(
            1,
            ShoppingAssistantConversationRouter::optionIndex(
                'دومی رو بیشتر توضیح بده و لینکش رو بفرست.'
            )
        );

        $this->assertSame(
            '/about',
            ShoppingAssistantConversationRouter::page(
                'Open the about us page'
            )['path']
        );
    }
    public function test_unknown_page_is_not_invented(): void
    {
        $this->assertNull(
            ShoppingAssistantConversationRouter::page(
                'Link to your secret page'
            )
        );

        $this->assertNull(
            ShoppingAssistantConversationRouter::page(
                'Recommend a collection of knives'
            )
        );
    }

    public function test_option_references(): void
    {
        $this->assertSame(
            0,
            ShoppingAssistantConversationRouter::optionIndex(
                'لینک اولی رو بده'
            )
        );

        $this->assertSame(
            1,
            ShoppingAssistantConversationRouter::optionIndex(
                'Tell me about the second one'
            )
        );

        $this->assertSame(
            2,
            ShoppingAssistantConversationRouter::optionIndex(
                'سومی رو توضیح بده'
            )
        );

        $this->assertNull(
            ShoppingAssistantConversationRouter::optionIndex(
                'Explain this one'
            )
        );
    }

    public function test_comparison_reference(): void
    {
        $this->assertTrue(
            ShoppingAssistantConversationRouter::comparing(
                'Compare these products'
            )
        );

        $this->assertTrue(
            ShoppingAssistantConversationRouter::comparing(
                'کدوم بهتره؟'
            )
        );

        $this->assertFalse(
            ShoppingAssistantConversationRouter::comparing(
                'Is it available?'
            )
        );
    }
}