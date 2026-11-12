<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class ShoppingAssistantUnavailable extends RuntimeException
{
    /**
     * @param array{
     *     failure_stage?: string,
     *     failure_category?: string,
     *     dispatch_state?: string,
     *     provider_http_status?: int|null,
     *     elapsed_ms?: int|null,
     *     provider_request_id?: string|null,
     *     response_finish_reason?: string|null,
     *     response_content_exists?: bool,
     *     response_content_type?: string,
     *     response_content_part_types?: array<int, string>,
     *     response_usage_present?: bool,
     *     response_usage_fields?: array<string, bool>,
     *     provider_error_category?: string|null
     * } $diagnostics
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        private readonly array $diagnostics = [],
        private readonly ?int $reservationId = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array{
     *     failure_stage: string,
     *     failure_category: string,
     *     dispatch_state: string,
     *     provider_http_status: int|null,
     *     elapsed_ms: int|null,
     *     provider_request_id: string|null,
     *     response_finish_reason: string|null,
     *     response_content_exists: bool,
     *     response_content_type: string,
     *     response_content_part_types: array<int, string>,
     *     response_usage_present: bool,
     *     response_usage_fields: array<string, bool>,
     *     provider_error_category: string|null
     * }
     */
    public function diagnostics(): array
    {
        return [
            'failure_stage' => $this->diagnostics['failure_stage'] ?? 'assistant',
            'failure_category' => $this->diagnostics['failure_category'] ?? 'internal_error',
            'dispatch_state' => $this->diagnostics['dispatch_state'] ?? 'unknown',
            'provider_http_status' => $this->diagnostics['provider_http_status'] ?? null,
            'elapsed_ms' => $this->diagnostics['elapsed_ms'] ?? null,
            'provider_request_id' => $this->diagnostics['provider_request_id'] ?? null,
            'response_finish_reason' => $this->diagnostics['response_finish_reason'] ?? null,
            'response_content_exists' => $this->diagnostics['response_content_exists'] ?? false,
            'response_content_type' => $this->diagnostics['response_content_type'] ?? 'unknown',
            'response_content_part_types' => $this->diagnostics['response_content_part_types'] ?? [],
            'response_usage_present' => $this->diagnostics['response_usage_present'] ?? false,
            'response_usage_fields' => $this->diagnostics['response_usage_fields'] ?? [],
            'provider_error_category' => $this->diagnostics['provider_error_category'] ?? null,
        ];
    }

    public function reservationId(): ?int
    {
        return $this->reservationId;
    }

    public function withReservationId(?int $reservationId): self
    {
        return new self(
            $this->getMessage(),
            $this->getCode(),
            $this,
            $this->diagnostics,
            $reservationId
        );
    }
}
