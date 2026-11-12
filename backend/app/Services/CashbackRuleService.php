<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CashbackRule;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

class CashbackRuleService
{
    public function resolveForProduct(
        Product $product,
        ?Carbon $at = null
    ): ?CashbackRule {
        $at ??= now();

        $categoryIds = $this->categoryPathIds(
            (int) $product->category_id
        );

        $rules = CashbackRule::query()
            ->where('is_active', true)
            ->where(function ($query) use ($at) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $at);
            })
            ->where(function ($query) use ($at) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $at);
            })
            ->with([
                'products:id',
                'categories:id',
            ])
            ->get();

        return $rules
            ->filter(
                fn (CashbackRule $rule): bool =>
                    $this->matchesProduct(
                        $rule,
                        (int) $product->id,
                        $categoryIds
                    )
            )
            ->sort(function (
                CashbackRule $a,
                CashbackRule $b
            ): int {
                $priorityComparison =
                    $b->priority <=> $a->priority;

                if ($priorityComparison !== 0) {
                    return $priorityComparison;
                }

                $scopeComparison =
                    $this->scopeRank($b->scope)
                    <=> $this->scopeRank($a->scope);

                if ($scopeComparison !== 0) {
                    return $scopeComparison;
                }

                return $a->id <=> $b->id;
            })
            ->first();
    }

    private function matchesProduct(
        CashbackRule $rule,
        int $productId,
        array $categoryIds
    ): bool {
        return match ($rule->scope) {
            'all' => true,

            'products' => $rule->products
                ->contains('id', $productId),

            'categories' => $rule->categories
                ->pluck('id')
                ->intersect($categoryIds)
                ->isNotEmpty(),

            default => false,
        };
    }

    private function scopeRank(string $scope): int
    {
        return match ($scope) {
            'products' => 3,
            'categories' => 2,
            'all' => 1,
            default => 0,
        };
    }

    private function categoryPathIds(int $categoryId): array
    {
        $ids = [];
        $visited = [];

        while ($categoryId > 0) {
            if (isset($visited[$categoryId])) {
                throw new RuntimeException(
                    'Circular category hierarchy detected.'
                );
            }

            $visited[$categoryId] = true;

            $category = Category::query()
                ->select(['id', 'parent_id'])
                ->find($categoryId);

            if (! $category) {
                break;
            }

            $ids[] = (int) $category->id;
            $categoryId = (int) ($category->parent_id ?? 0);
        }

        return $ids;
    }
}