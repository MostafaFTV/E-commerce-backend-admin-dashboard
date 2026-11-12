<?php

namespace App\Services;

use App\Enums\CartStatus;
use App\Enums\ProductStatus;
use App\Enums\UserStatus;
use App\Enums\VariantStatus;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{
    public const GUEST_COOKIE = 'tkb_guest_cart_token';

    public const GUEST_HEADER = 'X-TKB-Guest-Token';

    /*
    |--------------------------------------------------------------------------
    | Storefront Mode
    |--------------------------------------------------------------------------
    */

    public function shouldEnforceInventory(): bool
    {
        return (bool) config(
            'storefront.online_checkout_enabled',
            false
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve Cart
    |--------------------------------------------------------------------------
    */

    public function resolve(
        Request $request,
        bool $create = true
    ): array {
        $user = auth('sanctum')->user();

        /*
         * Authenticated users must have an active account.
         *
         * Cart routes intentionally remain publicly accessible because
         * guest carts are supported. Therefore the active-user protection
         * cannot be delegated to route middleware here and must be enforced
         * at the authenticated-user branch.
         */
        if ($user) {
            $this->ensureActiveUser($user);

            $cart = Cart::activeForUser($user);

            if (! $cart && $create) {
                $cart = Cart::create([
                    'user_id' => $user->id,
                    'status' => CartStatus::ACTIVE,
                ]);
            }

            return [
                'cart' => $cart,
                'guest_token' => null,
            ];
        }

        /*
         * Guest cart resolution is intentionally allowed without
         * authentication.
         */
        $token =
            $request->header(self::GUEST_HEADER)
            ?: $request->cookie(self::GUEST_COOKIE);

        $cart = $token
            ? Cart::query()
                ->where(
                    'session_id',
                    $token
                )
                ->where(
                    'status',
                    CartStatus::ACTIVE
                )
                ->latest('id')
                ->first()
            : null;

        if (! $cart && $create) {
            $token =
                $this->newGuestToken();

            $cart = Cart::create([
                'session_id' => $token,
                'status' => CartStatus::ACTIVE,
            ]);
        }

        return [
            'cart' => $cart,
            'guest_token' => $token,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Merge Guest Cart
    |--------------------------------------------------------------------------
    */

    public function mergeGuestCart(
        User $user,
        ?string $token
    ): void {
        $this->ensureActiveUser($user);

        if (! $token) {
            return;
        }

        DB::transaction(
            function () use (
                $user,
                $token
            ): void {

                $guestCart = Cart::query()
                    ->where(
                        'session_id',
                        $token
                    )
                    ->where(
                        'status',
                        CartStatus::ACTIVE
                    )
                    ->lockForUpdate()
                    ->first();

                if (! $guestCart) {
                    return;
                }

                $userCart = Cart::query()
                    ->where(
                        'user_id',
                        $user->id
                    )
                    ->where(
                        'status',
                        CartStatus::ACTIVE
                    )
                    ->latest('id')
                    ->lockForUpdate()
                    ->first();

                if (! $userCart) {
                    $guestCart->load('items');

                    foreach (
                        $guestCart->items
                        as $guestItem
                    ) {
                        if (
                            $guestItem->product_variant_id
                            !== null
                        ) {
                            $variant =
                                $this->availableVariant(
                                    $guestItem
                                        ->product_variant_id
                                );

                            $this->ensureItemCanBeAdded(
                                $variant,
                                $guestItem->quantity
                            );

                            continue;
                        }

                        $this->availableProduct(
                            (int) $guestItem->product_id
                        );
                    }

                    $guestCart->update([
                        'user_id' =>
                            $user->id,

                        'session_id' =>
                            null,
                    ]);

                    return;
                }

                $guestCart->load('items');
                $userCart->load('items');

                foreach (
                    $guestCart->items
                    as $guestItem
                ) {
                    if (
                        $guestItem->product_variant_id
                        !== null
                    ) {
                        $variant =
                            $this->availableVariant(
                                $guestItem
                                    ->product_variant_id
                            );

                        $userItem =
                            $userCart
                                ->items
                                ->firstWhere(
                                    'product_variant_id',
                                    $variant->id
                                );

                        $quantity =
                            ($userItem?->quantity ?? 0)
                            + $guestItem->quantity;

                        $this->ensureItemCanBeAdded(
                            $variant,
                            $quantity
                        );

                        if ($userItem) {
                            $userItem->update([
                                'quantity' =>
                                    $quantity,
                            ]);
                        } else {
                            $userCart->items()->create([
                                'product_id' =>
                                    $variant->product_id,

                                'product_variant_id' =>
                                    $variant->id,

                                'quantity' =>
                                    $guestItem->quantity,

                                'price_snapshot' =>
                                    $guestItem->price_snapshot,
                            ]);
                        }

                        continue;
                    }

                    $product =
                        $this->availableProduct(
                            (int) $guestItem->product_id
                        );

                    $userItem =
                        $userCart
                            ->items
                            ->first(
                                fn ($item) =>
                                    $item->product_variant_id
                                        === null
                                    && (int) $item->product_id
                                        === (int) $product->id
                            );

                    $quantity =
                        ($userItem?->quantity ?? 0)
                        + $guestItem->quantity;

                    if ($userItem) {
                        $userItem->update([
                            'quantity' =>
                                $quantity,
                        ]);
                    } else {
                        $userCart->items()->create([
                            'product_id' =>
                                $product->id,

                            'product_variant_id' =>
                                null,

                            'quantity' =>
                                $guestItem->quantity,

                            'price_snapshot' =>
                                $guestItem->price_snapshot,
                        ]);
                    }
                }

                $guestCart->update([
                    'status' =>
                        CartStatus::CONVERTED,
                ]);
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Product Availability
    |--------------------------------------------------------------------------
    */

    public function availableProduct(
        int $productId
    ): Product {
        $product = Product::query()
            ->withCount('variants')
            ->whereKey($productId)
            ->where(
                'status',
                ProductStatus::ACTIVE
            )
            ->whereHas(
                'category',
                fn ($query) =>
                    $query->where(
                        'is_active',
                        true
                    )
            )
            ->first();

        if (! $product) {
            throw ValidationException::withMessages([
                'product_id' =>
                    'This product is not currently available.',
            ]);
        }

        /*
         * Product-only cart items are reserved exclusively for products
         * that genuinely have no variants.
         *
         * A product with variants must keep using the explicit
         * product_variant_id flow. No silent fallback is allowed.
         */
        if ((int) $product->variants_count > 0) {
            throw ValidationException::withMessages([
                'product_variant_id' =>
                    'Please select a product variant.',
            ]);
        }

        /*
         * The current catalog stores physical inventory per variant.
         * Product-only items are therefore allowed in the current
         * contact-checkout mode, where cart inventory is intentionally
         * not enforced.
         *
         * If normal online checkout is enabled again, keep the existing
         * inventory guarantees strict until a product-level inventory
         * source is explicitly introduced.
         */
        if ($this->shouldEnforceInventory()) {
            throw ValidationException::withMessages([
                'product_id' =>
                    'This product is not currently available for online checkout.',
            ]);
        }

        return $product;
    }

    /*
    |--------------------------------------------------------------------------
    | Variant Availability
    |--------------------------------------------------------------------------
    */

    public function availableVariant(
        int $variantId
    ): ProductVariant {
        $variant = ProductVariant::query()
            ->with([
                'product',
                'inventory',
            ])
            ->whereKey($variantId)
            ->whereIn(
                'status',
                [
                    VariantStatus::ACTIVE,
                    VariantStatus::MADE_TO_ORDER,
                ]
            )
            ->whereHas(
                'product',
                fn ($query) =>
                    $query->where(
                        'status',
                        ProductStatus::ACTIVE
                    )
            )
            ->first();

        if (! $variant) {
            throw ValidationException::withMessages([
                'product_variant_id' =>
                    'This product variant is not currently available.',
            ]);
        }

        /*
         * During contact-checkout mode, ACTIVE variants are allowed
         * in the cart even when inventory is not currently tracked.
         *
         * When online checkout is enabled again, the original strict
         * inventory requirement automatically returns.
         */
        if (
            $this->shouldEnforceInventory()
            && $variant->requiresInventory()
            && ! $variant->inventory
        ) {
            throw ValidationException::withMessages([
                'product_variant_id' =>
                    'This product variant is not currently available.',
            ]);
        }

        if (
            $this->shouldEnforceInventory()
            && $variant->requiresInventory()
            && $variant->inventory
        ) {
            $variant->setRelation(
                'inventory',
                $variant
                    ->inventory()
                    ->lockForUpdate()
                    ->first()
            );

            if (! $variant->inventory) {
                throw ValidationException::withMessages([
                    'product_variant_id' =>
                        'This product variant is not currently available.',
                ]);
            }
        }

        return $variant;
    }

    /*
    |--------------------------------------------------------------------------
    | Inventory Validation
    |--------------------------------------------------------------------------
    */

    public function ensureAvailable(
        int $quantity,
        int $available
    ): void {
        if ($quantity > $available) {
            throw ValidationException::withMessages([
                'quantity' =>
                    'The requested quantity exceeds available inventory.',
            ]);
        }
    }

    private function ensureItemCanBeAdded(
        ProductVariant $variant,
        int $quantity
    ): void {
        /*
         * Contact-checkout mode intentionally does not enforce stock
         * quantities at cart level.
         */
        if (! $this->shouldEnforceInventory()) {
            return;
        }

        if ($variant->isMadeToOrder()) {
            return;
        }

        $this->ensureAvailable(
            $quantity,
            $variant->inventory
                ? $variant->inventory->availableQuantity()
                : 0
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    private function ensureActiveUser(
        User $user
    ): void {
        if ($user->status !== UserStatus::ACTIVE) {
            abort(
                403,
                'Account is not active.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Guest Token
    |--------------------------------------------------------------------------
    */

    private function newGuestToken(): string
    {
        return bin2hex(
            random_bytes(32)
        );
    }
}
