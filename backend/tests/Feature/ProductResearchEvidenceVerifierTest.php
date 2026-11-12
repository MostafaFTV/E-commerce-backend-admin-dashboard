<?php

namespace Tests\Feature;

use App\Services\ShoppingAssistant\ProductResearchEvidenceVerifier;
use Tests\TestCase;

class ProductResearchEvidenceVerifierTest extends TestCase
{
    private function assess(
        string $model,
        string $answer,
        array $urls
    ): array {
        return app(ProductResearchEvidenceVerifier::class)->assess(
            'KitchenAid',
            $model,
            [
                'answer' => $answer,
                'citation_urls' => $urls,
                'verification_status' => 'unverified',
            ]
        );
    }

    public function test_exact_model_identifiers_produce_only_a_candidate(): void
    {
        $result = $this->assess(
            '5KSM175PS',
            'KitchenAid 5KSM175PS has a listed bowl capacity.',
            ['https://www.example.com/kitchenaid/5ksm175ps/manual']
        );

        $this->assertSame('candidate_exact_model', $result['status']);
        $this->assertFalse($result['approved']);
    }

    public function test_related_model_cannot_pass_as_exact_model(): void
    {
        $result = $this->assess(
            '5KSM175PS',
            'KitchenAid 5KSM175 has a 4.8 L bowl.',
            ['https://www.example.com/mixers/5ksm175']
        );

        $this->assertSame('needs_review', $result['status']);
        $this->assertFalse($result['approved']);
    }

    public function test_model_with_extra_suffix_is_not_an_exact_match(): void
    {
        $result = $this->assess(
            'KX-17',
            'KitchenAid KX-170 has a listed capacity.',
            ['https://www.example.com/products/kx-170']
        );

        $this->assertSame('needs_review', $result['status']);
        $this->assertFalse($result['approved']);
    }

    public function test_invalid_citations_fail_closed(): void
    {
        $result = $this->assess(
            '5KSM175PS',
            'KitchenAid 5KSM175PS has a listed capacity.',
            ['http://localhost/internal', 'https://127.0.0.1/private']
        );

        $this->assertSame('insufficient_evidence', $result['status']);
        $this->assertFalse($result['approved']);
        $this->assertSame([], $result['citation_urls']);
    }

    public function test_missing_manufacturer_requires_review(): void
    {
        $result = $this->assess(
            '5KSM175PS',
            'The 5KSM175PS has a listed capacity.',
            ['https://www.example.com/products/5ksm175ps']
        );

        $this->assertSame('needs_review', $result['status']);
        $this->assertFalse($result['approved']);
    }
}