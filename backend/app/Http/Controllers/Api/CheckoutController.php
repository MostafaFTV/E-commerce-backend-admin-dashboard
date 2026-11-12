<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlaceOrderRequest;
use App\Http\Requests\SetBillingAddressRequest;
use App\Http\Requests\SetShippingAddressRequest;
use App\Http\Requests\SetShippingMethodRequest;
use App\Http\Resources\CheckoutResource;
use App\Http\Resources\OrderResource;
use App\Services\CheckoutService;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(
        protected CheckoutService $checkoutService
    ) {}

    public function show(Request $request)
    {
        $this->ensureOnlineCheckoutEnabled();

        $checkout =
            $this->checkoutService
                ->resolveCheckout(
                    $request->user()
                );

        return new CheckoutResource(
            $checkout
        );
    }

    public function setShippingAddress(
        SetShippingAddressRequest $request
    ) {
        $this->ensureOnlineCheckoutEnabled();

        $checkout =
            $this->checkoutService
                ->resolveCheckout(
                    $request->user()
                );

        $checkout =
            $this->checkoutService
                ->setShippingAddress(
                    $checkout,
                    $request->validated()
                );

        return new CheckoutResource(
            $checkout
        );
    }

    public function setBillingAddress(
        SetBillingAddressRequest $request
    ) {
        $this->ensureOnlineCheckoutEnabled();

        $checkout =
            $this->checkoutService
                ->resolveCheckout(
                    $request->user()
                );

        $checkout =
            $this->checkoutService
                ->setBillingAddress(
                    $checkout,
                    $request->validated()
                );

        return new CheckoutResource(
            $checkout
        );
    }

    public function setShippingMethod(
        SetShippingMethodRequest $request
    ) {
        $this->ensureOnlineCheckoutEnabled();

        $checkout =
            $this->checkoutService
                ->resolveCheckout(
                    $request->user()
                );

        $checkout =
            $this->checkoutService
                ->setShippingMethod(
                    $checkout,
                    (int) $request->validated(
                        'shipping_method_id'
                    )
                );

        return new CheckoutResource(
            $checkout
        );
    }

    public function setWalletCredit(Request $request)
    {
        $this->ensureOnlineCheckoutEnabled();

        $validated = $request->validate([
            'amount' => [
                'required',
                'regex:/^(?:0|[1-9]\d{0,9})(?:\.\d{1,2})?$/',
            ],
        ]);

        $checkout = $this->checkoutService
            ->resolveCheckout($request->user());

        return new CheckoutResource(
            $this->checkoutService->setWalletCredit(
                $checkout,
                (string) $validated['amount']
            )
        );
    }

    public function placeOrder(
        PlaceOrderRequest $request
    ) {
        $this->ensureOnlineCheckoutEnabled();

        $checkout =
            $this->checkoutService
                ->resolveCheckout(
                    $request->user()
                );

        $order =
            $this->checkoutService
                ->placeOrder(
                    $request->user(),
                    $checkout
                );

        return new OrderResource(
            $order
        );
    }

    private function ensureOnlineCheckoutEnabled(): void
    {
        if (
            ! config(
                'storefront.online_checkout_enabled',
                false
            )
        ) {
            abort(
                503,
                'Online checkout is temporarily unavailable. Please contact us to complete your purchase.'
            );
        }
    }
}