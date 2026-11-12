<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\ValidationException;

class WishlistController extends Controller
{
    /**
     * Get the authenticated user's wishlist.
     */
    public function index(Request $request): JsonResource
    {
        $wishlist = Wishlist::query()
            ->where(
                'user_id',
                $request->user()->id
            )
            ->with([
                'items.product' => function ($query) {
                    $query->with([
                        'category',
                        'collections',
                        'images',
                        'variants.inventory',
                    ]);
                },
            ])
            ->first();

        if (! $wishlist) {
            return JsonResource::make([
                'id' => null,
                'items' => [],
            ]);
        }

        return JsonResource::make([
            'id' => $wishlist->id,
            'items' => $wishlist->items
                ->map(function (WishlistItem $item) {
                    return [
                        'id' => $item->id,
                        'added_at' => $item->created_at,
                        'product' => $item->product
                            ? new ProductResource(
                                $item->product
                            )
                            : null,
                    ];
                })
                ->values()
                ->all(),
        ]);
    }

    /**
     * Add a product to the authenticated user's wishlist.
     */
    public function store(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],
        ]);

        $product = Product::query()
            ->whereKey($validated['product_id'])
            ->whereNull('deleted_at')
            ->first();

        if (! $product) {
            throw ValidationException::withMessages([
                'product_id' =>
                    'The selected product is no longer available.',
            ]);
        }

        $wishlist = Wishlist::query()
            ->firstOrCreate([
                'user_id' => $request->user()->id,
            ]);

        $existingItem = WishlistItem::query()
            ->where(
                'wishlist_id',
                $wishlist->id
            )
            ->where(
                'product_id',
                $product->id
            )
            ->first();

        if ($existingItem) {
            return response()->json([
                'message' =>
                    'Product is already in the wishlist.',
                'item' => [
                    'id' => $existingItem->id,
                    'product_id' => $existingItem->product_id,
                    'created_at' => $existingItem->created_at,
                ],
            ]);
        }

        $item = WishlistItem::create([
            'wishlist_id' => $wishlist->id,
            'product_id' => $product->id,
            'created_at' => now(),
        ]);

        return response()->json([
            'message' =>
                'Product added to wishlist.',
            'item' => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'created_at' => $item->created_at,
            ],
        ], 201);
    }

    /**
     * Remove one item from the authenticated user's wishlist.
     */
    public function destroyItem(
        Request $request,
        WishlistItem $item
    ): JsonResponse {
        $wishlist = Wishlist::query()
            ->whereKey($item->wishlist_id)
            ->where(
                'user_id',
                $request->user()->id
            )
            ->first();

        if (! $wishlist) {
            return response()->json([
                'message' =>
                    'Wishlist item not found.',
            ], 404);
        }

        $item->delete();

        return response()->json([
            'message' =>
                'Product removed from wishlist.',
        ]);
    }

    /**
     * Remove all items from the authenticated user's wishlist.
     */
    public function destroy(
        Request $request
    ): JsonResponse {
        $wishlist = Wishlist::query()
            ->where(
                'user_id',
                $request->user()->id
            )
            ->first();

        if (! $wishlist) {
            return response()->json([
                'message' => 'Wishlist not found.',
            ], 404);
        }

        $wishlist->items()->delete();

        return response()->json([
            'message' =>
                'Wishlist cleared successfully.',
        ]);
    }
}
