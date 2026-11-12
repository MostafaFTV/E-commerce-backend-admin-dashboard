<?php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 */
class InventoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'quantity' => fake()->numberBetween(0, 1000),
            'reserved_quantity' => 0,
            'low_stock_threshold' => 5,
        ];
    }

    public function withReserved(int $reserved): static
    {
        return $this->state(fn (array $attributes) => [
            'reserved_quantity' => $reserved,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => 0,
            'reserved_quantity' => 0,
        ]);
    }

    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => 3,
            'reserved_quantity' => 0,
            'low_stock_threshold' => 5,
        ]);
    }
}
