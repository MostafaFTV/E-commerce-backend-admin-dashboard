<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Services\ShoppingAssistant\ProductResearchFactPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductResearchFactPublisherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config()->set('shopping_assistant_research.official_domains', [
            'samplebrand' => ['manufacturer.example'],
        ]);
    }

    private function product(string $name = 'SampleBrand MIX-123 stand mixer'): Product
    {
        $category = Category::factory()->create(['is_active' => true]);
        return Product::factory()->create([
            'category_id' => $category->id,
            'brand' => 'SampleBrand',
            'name' => ['en' => $name],
            'slug' => 'samplebrand-mix-123-stand-mixer',
            'status' => ProductStatus::ACTIVE,
        ]);
    }

    private function evidence(string $url, string $model = 'MIX-123'): array
    {
        return [
            'verification_status' => 'unverified',
            'citation_urls' => [$url],
            'answer' => json_encode([
                'model' => $model,
                'facts' => [[
                    'name' => 'Capacity', 'value' => '4.8 L', 'source_url' => $url,
                ]],
            ]),
        ];
    }

    public function test_exact_catalog_model_and_real_manufacturer_page_can_publish(): void
    {
        $product = $this->product();
        $url = 'https://manufacturer.example/mix-123/specs';
        Http::fake([$url => Http::response('<html>SampleBrand MIX-123 has capacity 4.8 L.</html>', 200, [
            'Content-Type' => 'text/html',
        ])]);
        $result = app(ProductResearchFactPublisher::class)->publish($product, $this->evidence($url));
        $this->assertTrue($result['approved']);
        $this->assertSame(1, $result['fact_count']);
        $this->assertDatabaseHas('shopping_assistant_product_knowledge', [
            'product_id' => $product->id, 'review_status' => 'approved', 'model' => 'MIX-123',
        ]);
    }

    public function test_suspicious_host_cannot_publish_or_be_fetched(): void
    {
        $product = $this->product();
        $url = 'https://manufacturer.example.evil.invalid/mix-123';
        $result = app(ProductResearchFactPublisher::class)->publish($product, $this->evidence($url));
        $this->assertFalse($result['approved']);
        Http::assertSentCount(0);
    }

    public function test_related_model_or_missing_source_fact_does_not_publish(): void
    {
        $product = $this->product();
        $url = 'https://manufacturer.example/mix-123/specs';
        Http::fake([$url => Http::response('SampleBrand MIX-123 has different specifications.', 200, [
            'Content-Type' => 'text/plain',
        ])]);
        $this->assertFalse(app(ProductResearchFactPublisher::class)->publish(
            $product, $this->evidence($url)
        )['approved']);
        $this->assertFalse(app(ProductResearchFactPublisher::class)->publish(
            $product, $this->evidence($url, 'MIX-123X')
        )['approved']);
        $this->assertDatabaseCount('shopping_assistant_product_knowledge', 0);
    }

    public function test_ambiguous_catalog_identity_cannot_publish(): void
    {
        $product = $this->product('SampleBrand Stand Mixer');
        $product->slug = 'samplebrand-stand-mixer';
        $product->save();
        $url = 'https://manufacturer.example/mix-123';
        $result = app(ProductResearchFactPublisher::class)->publish($product, $this->evidence($url));
        $this->assertFalse($result['approved']);
        Http::assertSentCount(0);
    }

    public function test_financial_claim_is_never_published(): void
    {
        $product = $this->product();
        $url = 'https://manufacturer.example/mix-123';
        $research = $this->evidence($url);
        $research['answer'] = json_encode(['model' => 'MIX-123', 'facts' => [[
            'name' => 'Price', 'value' => '$ 200', 'source_url' => $url,
        ]]]);
        $this->assertFalse(app(ProductResearchFactPublisher::class)->publish($product, $research)['approved']);
        Http::assertSentCount(0);
    }
}