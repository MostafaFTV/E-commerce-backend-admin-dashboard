<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Payment;
use Illuminate\Support\Str;

class FakePaymentGateway implements PaymentGatewayInterface
{
    public function requestPayment(Payment $payment): array
    {
        return [
            'reference' => 'FAKE-' . Str::upper(Str::random(20)),
            'payment_url' => 'http://fake-gateway.test/pay/' . $payment->id,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'status' => 'pending',
        ];
    }

    public function extractReference(array $callbackData): string
    {
        return trim(
            (string) ($callbackData['reference'] ?? '')
        );
    }

    public function verifyPayment(
        Payment $payment,
        array $callbackData
    ): array {
        $status = $callbackData['gateway_status'] ?? 'failed';

        if ($status !== 'success') {
            return [
                'success' => false,
                'transaction_id' => null,
                'amount' => null,
                'currency' => null,
                'gateway_response' => [
                    'gateway_status' => $status,
                ],
            ];
        }

        return [
            'success' => true,
            'transaction_id' => $callbackData['transaction_id']
                ?? 'FAKE-TX-' . Str::upper(Str::random(20)),
            'amount' => $callbackData['amount']
                ?? (float) $payment->amount,
            'currency' => $callbackData['currency']
                ?? $payment->currency,
            'gateway_response' => $callbackData,
        ];
    }

    /**
     * Simulate a successful refund.
     */
    public function refundPayment(
        Payment $payment
    ): array {
        return [
            'success' => true,
            'refund_transaction_id' =>
                'FAKE-REFUND-' . Str::upper(Str::random(20)),
            'gateway_response' => [
                'status' => 'refunded',
                'payment_id' => $payment->id,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency,
            ],
        ];
    }
}
