<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class SmsIrService
{
    /**
     * Send a verification SMS through SMS.ir.
     *
     * @return array{
     *     message_id:int|null,
     *     cost:int|float|null
     * }
     */
    public function sendVerify(
        string $mobile,
        string $code
    ): array {
        $apiKey = config('smsir.api_key');

        if (
            ! is_string($apiKey)
            || trim($apiKey) === ''
        ) {
            throw new RuntimeException(
                'SMS.ir API key is not configured.'
            );
        }

        $baseUrl = rtrim(
            (string) config(
                'smsir.base_url',
                'https://api.sms.ir'
            ),
            '/'
        );

        $templateId = (int) config(
            'smsir.verify_template_id',
            123456
        );

        $timeout = max(
            1,
            (int) config(
                'smsir.timeout',
                10
            )
        );

        $response = Http::timeout($timeout)
            ->accept('text/plain')
            ->withHeaders([
                'x-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post(
                $baseUrl . '/v1/send/verify',
                [
                    'mobile' => $mobile,

                    'templateId' => $templateId,

                    'parameters' => [
                        [
                            'name' => 'Code',
                            'value' => $code,
                        ],
                    ],
                ]
            );

        if (! $response->successful()) {
            throw new RuntimeException(
                'SMS.ir request failed with HTTP status '
                . $response->status()
                . '.'
            );
        }

        $payload = $response->json();

        if (
            ! is_array($payload)
            || (int) ($payload['status'] ?? 0) !== 1
        ) {
            throw new RuntimeException(
                'SMS.ir rejected the verification SMS request.'
            );
        }

        return [
            'message_id' => isset(
                $payload['data']['messageId']
            )
                ? (int) $payload['data']['messageId']
                : null,

            'cost' => $payload['data']['cost'] ?? null,
        ];
    }
}