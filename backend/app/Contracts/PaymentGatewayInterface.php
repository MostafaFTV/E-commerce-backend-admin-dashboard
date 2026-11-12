<?php

namespace App\Contracts;

use App\Models\Payment;

interface PaymentGatewayInterface
{
    /**
     * Create a payment request at the gateway.
     *
     * Returns:
     * - reference: gateway authority/token/reference
     * - payment_url: customer redirect URL
     */
    public function requestPayment(Payment $payment): array;

    /**
     * Extract the gateway reference from callback data.
     *
     * This is gateway-specific because different PSPs
     * use different callback parameter names.
     */
    public function extractReference(array $callbackData): string;

    /**
     * Verify payment result with the gateway.
     *
     * Expected successful result:
     * - success: bool
     * - transaction_id: nullable string
     * - amount: nullable numeric
     * - currency: nullable string
     * - gateway_response: array
     */
    public function verifyPayment(
        Payment $payment,
        array $callbackData
    ): array;

    /**
     * Refund a previously paid payment.
     *
     * Expected response:
     * - success: bool
     * - refund_transaction_id: nullable string
     * - gateway_response: array
     */
    public function refundPayment(
        Payment $payment
    ): array;
}
