<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ShoppingAssistantProductKnowledge;
use App\Services\ShoppingAssistant\ProductKnowledgeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ShoppingAssistantProductKnowledgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'shopping_assistant.driver' => 'gapgpt',
            'shopping_assistant.api_key' => 'offline-test-key',
            'shopping_assistant.endpoint' => 'chat_completions',
            'shopping_assistant.daily_budget_usd' => null,
            'shopping_assistant.monthly_budget_usd' => null,
        ]);
        Http::preventStrayRequests();
        RateLimiter::clear('shopping-assistant-ip-minute:127.0.0.1');
        RateLimiter::clear('shopping-assistant-ip-hour:127.0.0.1');
    }

    public function test_exact_approved_model_facts_and_source_are_injected_and_product_update_stales_them(): void
    {
        $product = $this->createProduct();
        $sourceUrl = 'https://support.acme.example/manuals/kx-17';
        $knowledge = $this->createKnowledge($product, [
            'manufacturer' => 'Acme',
            'model' => 'KX-17',
            'facts' => [[
                'name' => 'Capacity',
                'value' => '1.5 L',
                'source_url' => $sourceUrl,
                'review_status' => 'approved',
            ]],
            'sources' => [[
                'url' => $sourceUrl,
                'source_type' => 'manufacturer_documentation',
                'review_status' => 'approved',
            ]],
        ]);
        Http::fake(fn () => Http::response([
            'choices' => [[
                'message' => ['content' => 'The Acme KX-17 kettle lists a 1.5 L capacity.'],
            ]],
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 12],
        ]));

        $this->postJson('/api/assistant/chat?locale=en', [
            'message' => 'Recommend the Acme KX-17 kettle.',
        ])->assertOk()
            ->assertJsonPath('data.product_cards.0.id', $product->id);

        Http::assertSent(function ($request) use ($product, $sourceUrl, $knowledge): bool {
            $payload = json_decode($request->body(), true);
            $systemMessage = (string) data_get($payload, 'messages.0.content');
            $this->assertStringContainsString('verified_product_knowledge', $systemMessage);
            $this->assertStringContainsString('"product_id":'.$product->id, $systemMessage);
            $this->assertStringContainsString('1.5 L', $systemMessage);
            $this->assertStringContainsString($sourceUrl, $systemMessage);
            $this->assertStringContainsString($knowledge->verified_at->toIso8601String(), $systemMessage);

            return true;
        });

        $product->update(['short_description' => ['en' => 'Updated catalog copy.']]);
        $this->assertDatabaseHas('shopping_assistant_product_knowledge', [
            'id' => $knowledge->id,
            'review_status' => 'stale',
            'verified_at' => null,
        ]);
        $knowledge->update(['review_status' => 'approved']);
        $context = app(ProductKnowledgeContext::class)->enrich([
            'products' => [['id' => $product->id]],
        ]);
        $this->assertArrayNotHasKey('verified_product_knowledge', $context);
    }

    public function test_wrong_model_or_manufacturer_never_matches_the_catalog_product(): void
    {
        $product = $this->createProduct();
        $knowledge = $this->createKnowledge($product, [
            'manufacturer' => 'Acme',
            'model' => 'KX-71',
            'facts' => [[
                'name' => 'Capacity',
                'value' => '1.5 L',
                'source_url' => 'https://support.acme.example/manuals/kx-17',
                'review_status' => 'approved',
            ]],
            'sources' => [[
                'url' => 'https://support.acme.example/manuals/kx-17',
                'source_type' => 'manufacturer_documentation',
                'review_status' => 'approved',
            ]],
        ]);

        $context = app(ProductKnowledgeContext::class)->enrich([
            'products' => [['id' => $product->id]],
        ]);
        $this->assertArrayNotHasKey('verified_product_knowledge', $context);

        $knowledge->delete();
        $this->createKnowledge($product, [
            'manufacturer' => 'Other Brand',
            'model' => 'KX-17',
            'facts' => [[
                'name' => 'Capacity',
                'value' => '1.5 L',
                'source_url' => 'https://support.acme.example/manuals/kx-17',
                'review_status' => 'approved',
            ]],
            'sources' => [[
                'url' => 'https://support.acme.example/manuals/kx-17',
                'source_type' => 'manufacturer_documentation',
                'review_status' => 'approved',
            ]],
        ]);
        $this->assertArrayNotHasKey(
            'verified_product_knowledge',
            app(ProductKnowledgeContext::class)->enrich([
                'products' => [['id' => $product->id]],
            ])
        );
    }

    public function test_missing_conflicting_and_untrusted_source_facts_are_omitted(): void
    {
        $product = $this->createProduct();
        $sourceUrl = 'https://support.acme.example/manuals/kx-17';
        $knowledge = $this->createKnowledge($product, [
            'manufacturer' => 'Acme',
            'model' => 'KX-17',
            'facts' => [
                [
                    'name' => 'Capacity',
                    'value' => '1.5 L',
                    'source_url' => $sourceUrl,
                    'review_status' => 'conflicting',
                ],
                [
                    'name' => 'Power',
                    'value' => '1200 W',
                    'source_url' => 'https://unverified.example/specs',
                    'review_status' => 'approved',
                ],
                [
                    'name' => 'Price',
                    'value' => '100 Toman',
                    'source_url' => $sourceUrl,
                    'review_status' => 'approved',
                ],
                [
                    'name' => 'Availability',
                    'value' => 'In stock',
                    'source_url' => $sourceUrl,
                    'review_status' => 'approved',
                ],
            ],
            'sources' => [
                [
                    'url' => $sourceUrl,
                    'source_type' => 'manufacturer_documentation',
                    'review_status' => 'approved',
                ],
                [
                    'url' => 'https://unverified.example/specs',
                    'source_type' => 'untrusted',
                    'review_status' => 'approved',
                ],
            ],
        ]);

        $context = app(ProductKnowledgeContext::class)->enrich([
            'products' => [['id' => $product->id]],
        ]);
        $this->assertArrayNotHasKey('verified_product_knowledge', $context);

        $knowledge->update(['review_status' => 'conflicted']);
        $this->assertArrayNotHasKey(
            'verified_product_knowledge',
            app(ProductKnowledgeContext::class)->enrich([
                'products' => [['id' => $product->id]],
            ])
        );
    }

    public function test_unpublished_product_invalidates_assistant_knowledge(): void
    {
        $product = $this->createProduct();
        $knowledge = $this->createKnowledge($product, [
            'facts' => [[
                'name' => 'Capacity',
                'value' => '1.5 L',
                'source_url' => 'https://support.acme.example/manuals/kx-17',
                'review_status' => 'approved',
            ]],
            'sources' => [[
                'url' => 'https://support.acme.example/manuals/kx-17',
                'source_type' => 'manufacturer_documentation',
                'review_status' => 'approved',
            ]],
        ]);

        $product->update(['status' => ProductStatus::INACTIVE]);

        $this->assertDatabaseHas('shopping_assistant_product_knowledge', [
            'id' => $knowledge->id,
            'review_status' => 'stale',
            'verified_at' => null,
        ]);
        $this->assertArrayNotHasKey(
            'verified_product_knowledge',
            app(ProductKnowledgeContext::class)->enrich([
                'products' => [['id' => $product->id]],
            ])
        );
    }

    public function test_product_removal_deletes_its_assistant_knowledge(): void
    {
        $product = $this->createProduct();
        $knowledge = $this->createKnowledge($product, []);

        $product->forceDelete();

        $this->assertDatabaseMissing('shopping_assistant_product_knowledge', [
            'id' => $knowledge->id,
        ]);
    }

    public function test_unrelated_product_updates_preserve_approved_knowledge(): void
    {
        $product = $this->createProduct();
        $knowledge = $this->createKnowledge($product, []);

        $product->update([
            'seo_title' => ['en' => 'Updated SEO title'],
            'is_featured' => ! $product->is_featured,
        ]);

        $knowledge->refresh();
        $product->refresh();

        $this->assertSame('approved', $knowledge->review_status);
        $this->assertNotNull($knowledge->verified_at);
        $this->assertTrue(
            $knowledge->product_updated_at->equalTo($product->updated_at)
        );

        $product->update(['name' => ['en' => 'Acme KX-18 kettle']]);

        $knowledge->refresh();

        $this->assertSame('stale', $knowledge->review_status);
        $this->assertNull($knowledge->verified_at);
    }
    public function test_three_knowledge_levels_allow_guidance_without_unverified_facts(): void
    {
        $product = $this->createProduct();
        $resolver = app(ProductKnowledgeContext::class);
        $context = ['products' => [['id' => $product->id]]];

        // Level 3: No knowledge. Product remains eligible for advice.
        $withoutKnowledge = $resolver->enrich($context);

        $this->assertSame($context['products'], $withoutKnowledge['products']);
        $this->assertArrayNotHasKey(
            'verified_product_knowledge',
            $withoutKnowledge
        );

        $sourceUrl = 'https://support.acme.example/manuals/kx-17';

        $knowledge = $this->createKnowledge($product, [
            'review_status' => 'pending',
            'facts' => [
                [
                    'name' => 'Capacity',
                    'value' => '1.5 L',
                    'source_url' => $sourceUrl,
                    'review_status' => 'approved',
                ],
            ],
            'sources' => [
                [
                    'url' => $sourceUrl,
                    'source_type' => 'manufacturer_documentation',
                    'review_status' => 'approved',
                ],
            ],
        ]);

        // Level 2: Unapproved evidence must not become a product fact.
        $pending = $resolver->enrich($context);

        $this->assertSame($context['products'], $pending['products']);
        $this->assertArrayNotHasKey('verified_product_knowledge', $pending);

        $knowledge->update([
            'review_status' => 'approved',
            'verified_at' => now(),
        ]);

        // Level 1: Approved, exact-model evidence is available.
        $approved = $resolver->enrich($context);
        $facts = $approved['verified_product_knowledge'][0]['facts'] ?? [];

        $this->assertCount(1, $facts);
        $this->assertSame('Capacity', $facts[0]['name']);
        $this->assertSame('1.5 L', $facts[0]['value']);
        $this->assertSame($sourceUrl, $facts[0]['source_url']);

        // Conflicting evidence must not be sent as verified knowledge.
        $knowledge->update([
            'facts' => [
                [
                    'name' => 'Capacity',
                    'value' => '2.0 L',
                    'source_url' => $sourceUrl,
                    'review_status' => 'conflicting',
                ],
            ],
        ]);

        $conflicting = $resolver->enrich($context);

        $this->assertArrayNotHasKey(
            'verified_product_knowledge',
            $conflicting
        );
        $this->assertSame($context['products'], $conflicting['products']);
    }
    private function createProduct(): Product
    {
        $category = Category::factory()->create([
            'name' => ['en' => 'Kitchenware'],
        ]);

        return Product::factory()->create([
            'category_id' => $category->id,
            'name' => ['en' => 'Acme KX-17 kettle'],
            'brand' => 'Acme',
            'slug' => 'acme-kx-17-kettle',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createKnowledge(
        Product $product,
        array $attributes
    ): ShoppingAssistantProductKnowledge {
        return ShoppingAssistantProductKnowledge::query()->create([
            'product_id' => $product->id,
            'manufacturer' => 'Acme',
            'model' => 'KX-17',
            'facts' => [],
            'sources' => [],
            'verified_at' => now(),
            'review_status' => 'approved',
            ...$attributes,
        ]);
    }
}
