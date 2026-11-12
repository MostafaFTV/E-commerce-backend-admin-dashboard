<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\OrderActionForbiddenException;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Create a payment for the authenticated customer's order.
     */
    public function create(
        Request $request,
        Order $order
    ): PaymentResource {
        $this->ensureOnlineCheckoutEnabled();

        if (
            $order->user_id
            !== $request->user()->id
        ) {
            throw new OrderActionForbiddenException(
                'You are not allowed to pay for this order.'
            );
        }

        $result =
            $this->paymentService
                ->createPayment(
                    $order
                );

        return PaymentResource::withPaymentUrl(
            $result['payment'],
            $result['payment_url']
        );
    }

    /**
     * Handle payment gateway callback.
     *
     * This endpoint intentionally remains available even while
     * new online payments are disabled, because a payment that
     * was initiated earlier may still return from the gateway.
     */
    public function callback(
        Request $request
    ) {
        $payment =
            $this->paymentService
                ->handleCallback(
                    $request->query()
                );

        return response()->json([
            'message' =>
                $payment->isPaid()
                    ? 'Payment completed successfully.'
                    : 'Payment failed.',

            'payment' => [
                'status' =>
                    $payment->status?->value,

                'paid_at' =>
                    $payment->paid_at
                        ?->toISOString(),
            ],
        ]);
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
                'Online payment is temporarily unavailable.'
            );
        }
    }
}