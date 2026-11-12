<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public ?string $paymentUrl = null;

    public static function withPaymentUrl(
        $resource,
        ?string $paymentUrl = null
    ): self {
        $payment = new static($resource);
        $payment->paymentUrl = $paymentUrl;

        return $payment;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'order_id' => $this->order_id,

            'status' => $this->status?->value,

            'amount' => (float) $this->amount,

            'currency' => $this->currency,

            'method' => $this->method,

            'gateway_reference' => $this->gateway_reference,

            'transaction_id' => $this->transaction_id,

            'payment_url' => $this->paymentUrl,

            'paid_at' => $this->paid_at?->toISOString(),

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,
        ];
    }
}
