<?php

namespace App\Services\ShoppingAssistant;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

final class OnDemandProductResearchRegistry
{
    /**
     * Register a product for later research.
     * Never calls the research API.
     * Returns true only for the first successful registration.
     */
    public function register(Product $product): bool
    {
        if (! config('shopping_assistant_research.enabled', false)) {
            return false;
        }

        if (
            ! $product->exists ||
            $product->trashed() ||
            $product->status !== ProductStatus::ACTIVE ||
            ! $product->category()->where('is_active', true)->exists()
        ) {
            return false;
        }

        $brand = trim((string) $product->getRawOriginal('brand'));

        if ($brand === '') {
            return false;
        }

        $now = now();

        return DB::table('shopping_assistant_research_requests')
            ->insertOrIgnore([
                'product_id' => $product->id,
                'status' => 'pending',
                'attempts' => 0,
                'product_updated_at' => $product->updated_at,
                'created_at' => $now,
                'updated_at' => $now,
            ]) === 1;
    }
}