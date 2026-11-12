<?php

namespace App\Services;

use App\Enums\CartStatus;
use App\Enums\CheckoutSessionStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Models\Cart;
use App\Models\CheckoutSession;
use App\Models\Order;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        protected ActivityLogService $activityLogService
    ) {}

    public function resolveCheckout(User $user): CheckoutSession
    {
        return DB::transaction(function () use ($user): CheckoutSession {
            $cart = Cart::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->where(
                    'status',
                    CartStatus::ACTIVE
                )
                ->with([
                    'items.variant.product.category',
                    'items.variant.inventory',
                ])
                ->lockForUpdate()
                ->first();

            if (! $cart) {
                throw ValidationException::withMessages([
                    'cart' =>
                        'You do not have an active cart.',
                ]);
            }

            if ($cart->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' =>
                        'Your cart is empty.',
                ]);
            }

            foreach ($cart->items as $item) {
                $variant = $item->variant;

                if (
                    ! $variant ||
                    ! $variant->isPurchasable() ||
                    ! $variant->product ||
                    $variant->product->status !==
                        ProductStatus::ACTIVE ||
                    ! $variant->product->category ||
                    ! $variant->product->category->is_active
                ) {
                    throw ValidationException::withMessages([
                        'cart' =>
                            'One or more products in your cart are no longer available.',
                    ]);
                }

                if ($variant->requiresInventory()) {
                    if (! $variant->inventory) {
                        throw ValidationException::withMessages([
                            'cart' =>
                                'One or more products in your cart are unavailable.',
                        ]);
                    }

                    if (
                        $item->quantity >
                        $variant->inventory->availableQuantity()
                    ) {
                        throw ValidationException::withMessages([
                            'cart' =>
                                'One or more products in your cart no longer have enough inventory.',
                        ]);
                    }
                }
            }

            $checkout = CheckoutSession::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->where(
                    'cart_id',
                    $cart->id
                )
                ->where(
                    'status',
                    CheckoutSessionStatus::ACTIVE
                )
                ->lockForUpdate()
                ->first();

            if ($checkout) {
                return $checkout->load([
                    'cart.items.variant.product.category',
                    'cart.items.variant.inventory',
                    'shippingMethod',
                ]);
            }

            $checkout = CheckoutSession::create([
                'user_id' =>
                    $user->id,
                'cart_id' =>
                    $cart->id,
                'status' =>
                    CheckoutSessionStatus::ACTIVE,
            ]);

            return $checkout->load([
                'cart.items.variant.product.category',
                'cart.items.variant.attributeValues.attribute',
                'cart.items.variant.images',
                'cart.items.variant.inventory',
                'shippingMethod',
            ]);
        });
    }

    public function setShippingAddress(
        CheckoutSession $checkout,
        array $address
    ): CheckoutSession {
        $this->ensureActiveCheckout($checkout);

        $checkout->update([
            'shipping_address' => $address,
        ]);

        return $checkout->fresh([
            'shippingMethod',
        ]);
    }

    public function setBillingAddress(
        CheckoutSession $checkout,
        array $address
    ): CheckoutSession {
        $this->ensureActiveCheckout($checkout);

        $checkout->update([
            'billing_address' => $address,
        ]);

        return $checkout->fresh([
            'shippingMethod',
        ]);
    }

    public function setShippingMethod(
        CheckoutSession $checkout,
        int $shippingMethodId
    ): CheckoutSession {
        $this->ensureActiveCheckout($checkout);

        $shippingMethod = ShippingMethod::query()
            ->whereKey(
                $shippingMethodId
            )
            ->where(
                'is_active',
                true
            )
            ->first();

        if (! $shippingMethod) {
            throw ValidationException::withMessages([
                'shipping_method_id' =>
                    'The selected shipping method is not available.',
            ]);
        }

        $checkout->update([
            'shipping_method_id' =>
                $shippingMethod->id,
        ]);

        return $checkout->fresh([
            'shippingMethod',
        ]);
    }

    public function setWalletCredit(
        CheckoutSession $checkout,
        string $amount
    ): CheckoutSession {
        $this->ensureActiveCheckout($checkout);

        if (! preg_match(
            '/^(?:0|[1-9]\d{0,9})(?:\.\d{1,2})?$/',
            $amount
        )) {
            throw ValidationException::withMessages([
                'amount' => 'Invalid wallet amount.',
            ]);
        }

        $checkout->update([
            'wallet_credit_requested' => $amount,
        ]);

        return $checkout->fresh(['shippingMethod']);
    }

    public function placeOrder(
        User $user,
        CheckoutSession $checkout
    ): Order {
        return DB::transaction(
            function () use (
                $user,
                $checkout
            ): Order {
                $checkout = CheckoutSession::query()
                    ->whereKey(
                        $checkout->id
                    )
                    ->where(
                        'user_id',
                        $user->id
                    )
                    ->lockForUpdate()
                    ->first();

                if (! $checkout) {
                    throw ValidationException::withMessages([
                        'checkout' =>
                            'Checkout session not found.',
                    ]);
                }

                $this->ensureActiveCheckout(
                    $checkout
                );

                $cart = Cart::query()
                    ->whereKey(
                        $checkout->cart_id
                    )
                    ->where(
                        'user_id',
                        $user->id
                    )
                    ->where(
                        'status',
                        CartStatus::ACTIVE
                    )
                    ->with([
                        'items.variant.product.category',
                    ])
                    ->lockForUpdate()
                    ->first();

                if (! $cart) {
                    throw ValidationException::withMessages([
                        'cart' =>
                            'The checkout cart is no longer available.',
                    ]);
                }

                if ($cart->items->isEmpty()) {
                    throw ValidationException::withMessages([
                        'cart' =>
                            'Your cart is empty.',
                    ]);
                }

                if (! $checkout->shipping_address) {
                    throw ValidationException::withMessages([
                        'shipping_address' =>
                            'A shipping address is required before placing the order.',
                    ]);
                }

                if (! $checkout->shipping_method_id) {
                    throw ValidationException::withMessages([
                        'shipping_method_id' =>
                            'A shipping method is required before placing the order.',
                    ]);
                }

                $shippingMethod = ShippingMethod::query()
                    ->whereKey(
                        $checkout->shipping_method_id
                    )
                    ->where(
                        'is_active',
                        true
                    )
                    ->lockForUpdate()
                    ->first();

                if (! $shippingMethod) {
                    throw ValidationException::withMessages([
                        'shipping_method_id' =>
                            'The selected shipping method is no longer available.',
                    ]);
                }

                $subtotal = 0.0;

                foreach ($cart->items as $item) {
                    $unitPrice =
                        (float) $item->price_snapshot;

                    $lineTotal =
                        $unitPrice * $item->quantity;

                    $subtotal +=
                        $lineTotal;
                }

                $discount = 0.0;
                $tax = 0.0;

                $shippingCost =
                    (float) $shippingMethod->price;

                $total =
                    $subtotal
                    - $discount
                    + $shippingCost
                    + $tax;

                /*
                 * The wallet may cover merchandise only.
                 * Keep at least 0.01 IRT for gateway payment
                 * until wallet-only payments are implemented.
                 */
                $requested = (string) (
                    $checkout->wallet_credit_requested ?? '0.00'
                );

                if (! preg_match(
                    '/^(?:0|[1-9]\d{0,9})(?:\.\d{1,2})?$/',
                    $requested
                )) {
                    throw ValidationException::withMessages([
                        'wallet_credit' => 'Invalid wallet amount.',
                    ]);
                }

                [$whole, $fraction] = array_pad(
                    explode('.', $requested, 2),
                    2,
                    '0'
                );

                $walletMinor = ((int) $whole * 100)
                    + (int) str_pad($fraction, 2, '0');

                $beforeWalletMinor = (int) round($total * 100);

                $merchandiseMinor = (int) round(
                    max(0, $subtotal - $discount) * 100
                );

                if (
                    $walletMinor > $merchandiseMinor ||
                    $walletMinor >= $beforeWalletMinor
                ) {
                    throw ValidationException::withMessages([
                        'wallet_credit' =>
                            'Wallet amount exceeds the permitted amount.',
                    ]);
                }

                $totalBeforeWallet = $beforeWalletMinor / 100;

                $walletUsed = $walletMinor / 100;

                $total = ($beforeWalletMinor - $walletMinor) / 100;

                $order = Order::create([
                    'user_id' =>
                        $user->id,
                    'order_number' =>
                        $this->generateOrderNumber(),
                    'status' =>
                        OrderStatus::PENDING_PAYMENT,
                    'subtotal' =>
                        $subtotal,
                    'discount' =>
                        $discount,
                    'shipping_cost' =>
                        $shippingCost,
                    'tax' =>
                        $tax,
                    'total' =>
                        $total,
                    'total_before_wallet' =>
                        $totalBeforeWallet,
                    'wallet_credit_used' =>
                        $walletUsed,
                    'currency' =>
                        'IRT',
                    'shipping_method_id' =>
                        $shippingMethod->id,
                    'shipping_address' =>
                        $checkout->shipping_address,
                    'billing_address' =>
                        $checkout->billing_address,
                ]);

                $this->activityLogService->log(
                    action: 'orders.created',
                    description: 'Order created.',
                    properties: [
                        'order' => [
                            'id' =>
                                $order->id,
                            'order_number' =>
                                $order->order_number,
                            'status' =>
                                $order->status->value,
                        ],
                        'user_id' =>
                            $user->id,
                        'subtotal' =>
                            $subtotal,
                        'shipping_cost' =>
                            $shippingCost,
                        'tax' =>
                            $tax,
                        'total' =>
                            $total,
                        'currency' =>
                            $order->currency,
                        'shipping_method_id' =>
                            $shippingMethod->id,
                    ],
                    subject: $order,
                    actor: $user
                );

                $inventoryService =
                    app(InventoryService::class);

                foreach ($cart->items as $item) {
                    $variant =
                        $item->variant;

                    if (
                        ! $variant ||
                        ! $variant->isPurchasable() ||
                        ! $variant->product ||
                        $variant->product->status !==
                            ProductStatus::ACTIVE ||
                        ! $variant->product->category ||
                        ! $variant->product->category->is_active
                    ) {
                        throw ValidationException::withMessages([
                            'cart' =>
                                'One or more products in your cart are no longer available.',
                        ]);
                    }

                    $inventory = null;

                    if ($variant->requiresInventory()) {
                        $inventory = $variant
                            ->inventory()
                            ->lockForUpdate()
                            ->first();

                        if (! $inventory) {
                            throw ValidationException::withMessages([
                                'cart' =>
                                    'One or more products in your cart are unavailable.',
                            ]);
                        }

                        if (
                            ! $inventory->hasStock(
                                $item->quantity
                            )
                        ) {
                            throw ValidationException::withMessages([
                                'cart' =>
                                    "Insufficient inventory for SKU {$variant->sku}.",
                            ]);
                        }
                    }

                    $unitPrice =
                        (float) $item->price_snapshot;

                    $lineTotal =
                        $unitPrice * $item->quantity;

                    $orderItem =
                        $order->items()->create([
                            'product_id' =>
                                $variant->product_id,
                            'product_variant_id' =>
                                $variant->id,
                            'product_name' =>
                                $variant->product->name,
                            'sku' =>
                                $variant->sku,
                            'quantity' =>
                                $item->quantity,
                            'unit_price' =>
                                $unitPrice,
                            'total' =>
                                $lineTotal,
                        ]);

                    if (
                        $variant->requiresInventory()
                        && $inventory
                    ) {
                        $inventoryService->reserveStock(
                            $inventory,
                            $item->quantity,
                            $order,
                            $user,
                            $orderItem
                        );
                    }
                }

                /*
                 * Freeze cashback rules and calculated amounts
                 * when the order is created.
                 *
                 * No wallet credit is issued at this stage.
                 */
                if ($walletMinor > 0) {
                    app(WalletSpendingService::class)
                        ->reserve(
                            $order,
                            number_format($walletUsed, 2, '.', '')
                        );
                }

                app(CashbackService::class)
                    ->snapshotOrder($order);

                $checkout->update([
                    'status' =>
                        CheckoutSessionStatus::COMPLETED,
                ]);

                $cart->update([
                    'status' =>
                        CartStatus::CONVERTED,
                ]);

                return $order->fresh([
                    'items',
                    'shippingMethod',
                ]);
            }
        );
    }

    private function generateOrderNumber(): string
    {
        do {
            $orderNumber =
                'TKB-' .
                now()->format(
                    'YmdHis'
                ) .
                '-' .
                Str::upper(
                    Str::random(6)
                );
        } while (
            Order::query()
                ->where(
                    'order_number',
                    $orderNumber
                )
                ->exists()
        );

        return $orderNumber;
    }

    private function ensureActiveCheckout(
        CheckoutSession $checkout
    ): void {
        if (! $checkout->isActive()) {
            throw ValidationException::withMessages([
                'checkout' =>
                    'This checkout session is no longer active.',
            ]);
        }

        if ($checkout->isExpired()) {
            throw ValidationException::withMessages([
                'checkout' =>
                    'This checkout session has expired.',
            ]);
        }
    }
}