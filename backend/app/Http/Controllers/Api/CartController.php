<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cartService
    ) {}

    public function index(Request $request)
    {
        $context = $this->cartService->resolve(
            $request
        );

        return $this->cartResponse(
            $context,
            200
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => [
                'nullable',
                'required_without:product_variant_id',
                'integer',
                'exists:products,id',
            ],
            'product_variant_id' => [
                'nullable',
                'required_without:product_id',
                'integer',
                'exists:product_variants,id',
            ],
            'quantity' => [
                'required',
                'integer',
                'min:1',
            ],
        ]);

        $context = DB::transaction(
            function () use (
                $request,
                $validated
            ): array {
                $context =
                    $this->cartService->resolve(
                        $request
                    );

                $cart = $context['cart'];

                $quantity =
                    (int) $validated['quantity'];

                /*
                 * Existing variant-based cart flow.
                 *
                 * This remains the preferred identity whenever a
                 * product_variant_id is explicitly supplied.
                 */
                if (
                    isset(
                        $validated['product_variant_id']
                    )
                ) {
                    $variant =
                        $this->cartService
                            ->availableVariant(
                                (int) $validated[
                                    'product_variant_id'
                                ]
                            );

                    if (
                        isset($validated['product_id'])
                        && (int) $validated['product_id']
                            !== (int) $variant->product_id
                    ) {
                        throw ValidationException::withMessages([
                            'product_variant_id' =>
                                'The selected product variant does not belong to this product.',
                        ]);
                    }

                    $item = $cart->items()
                        ->where(
                            'product_variant_id',
                            $variant->id
                        )
                        ->lockForUpdate()
                        ->first();

                    $newQuantity =
                        ($item?->quantity ?? 0)
                        + $quantity;

                    /*
                     * Inventory is enforced only when normal online checkout
                     * is enabled.
                     */
                    if (
                        $this->cartService
                            ->shouldEnforceInventory()
                        && $variant->requiresInventory()
                    ) {
                        $availableQuantity =
                            $variant->inventory
                                ?->availableQuantity()
                            ?? 0;

                        $this->cartService
                            ->ensureAvailable(
                                $newQuantity,
                                $availableQuantity
                            );
                    }

                    if ($item) {
                        $item->update([
                            'quantity' =>
                                $newQuantity,
                        ]);
                    } else {
                        $cart->items()->create([
                            'product_id' =>
                                $variant->product_id,

                            'product_variant_id' =>
                                $variant->id,

                            'quantity' =>
                                $quantity,

                            'price_snapshot' =>
                                $variant->effectivePrice(),
                        ]);
                    }

                    return $context;
                }

                /*
                 * Product-only cart flow.
                 *
                 * This path is valid only for a product that genuinely has
                 * no variants. It never silently selects or creates a variant.
                 */
                $product =
                    $this->cartService
                        ->availableProduct(
                            (int) $validated[
                                'product_id'
                            ]
                        );

                $item = $cart->items()
                    ->where(
                        'product_id',
                        $product->id
                    )
                    ->whereNull(
                        'product_variant_id'
                    )
                    ->lockForUpdate()
                    ->first();

                $newQuantity =
                    ($item?->quantity ?? 0)
                    + $quantity;

                if ($item) {
                    $item->update([
                        'quantity' =>
                            $newQuantity,
                    ]);
                } else {
                    $cart->items()->create([
                        'product_id' =>
                            $product->id,

                        'product_variant_id' =>
                            null,

                        'quantity' =>
                            $quantity,

                        'price_snapshot' =>
                            (float) $product->price,
                    ]);
                }

                return $context;
            }
        );

        return $this->cartResponse(
            $context,
            201
        );
    }

    public function update(
        Request $request,
        int $item
    ) {
        $validated = $request->validate([
            'quantity' => [
                'required',
                'integer',
                'min:1',
            ],
        ]);

        $context = DB::transaction(
            function () use (
                $request,
                $item,
                $validated
            ): array {
                $context =
                    $this->cartService->resolve(
                        $request
                    );

                $cartItem =
                    $context['cart']
                        ->items()
                        ->whereKey($item)
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $cartItem->product_variant_id
                    !== null
                ) {
                    $variant =
                        $this->cartService
                            ->availableVariant(
                                $cartItem
                                    ->product_variant_id
                            );

                    /*
                     * Inventory is enforced only when normal online checkout
                     * is enabled.
                     */
                    if (
                        $this->cartService
                            ->shouldEnforceInventory()
                        && $variant->requiresInventory()
                    ) {
                        $availableQuantity =
                            $variant->inventory
                                ?->availableQuantity()
                            ?? 0;

                        $this->cartService
                            ->ensureAvailable(
                                (int) $validated[
                                    'quantity'
                                ],
                                $availableQuantity
                            );
                    }
                } else {
                    $this->cartService
                        ->availableProduct(
                            (int) $cartItem
                                ->product_id
                        );
                }

                $cartItem->update([
                    'quantity' =>
                        (int) $validated[
                            'quantity'
                        ],
                ]);

                return $context;
            }
        );

        return $this->cartResponse(
            $context,
            200
        );
    }

    public function destroyItem(
        Request $request,
        int $item
    ) {
        $context =
            $this->cartService->resolve(
                $request
            );

        $context['cart']
            ->items()
            ->whereKey($item)
            ->firstOrFail()
            ->delete();

        return response()->noContent();
    }

    public function destroy(
        Request $request
    ) {
        $context =
            $this->cartService->resolve(
                $request
            );

        $context['cart']
            ->items()
            ->delete();

        return response()->noContent();
    }

    private function cartResponse(
        array $context,
        int $status
    ) {
        $cart = $context['cart']->load([
            'items.product',
            'items.variant.product',
            'items.variant.attributeValues.attribute',
            'items.variant.images',
            'items.variant.inventory',
        ]);

        $response =
            (new CartResource($cart))
                ->response()
                ->setStatusCode($status);

        if ($context['guest_token']) {
            $response->withCookie(
                cookie(
                    CartService::GUEST_COOKIE,
                    $context['guest_token'],
                    60 * 24 * 30,
                    '/',
                    null,
                    app()->environment(
                        'production'
                    ),
                    true,
                    true,
                    'lax'
                )
            );
        }

        return $response;
    }
}
