<?php

namespace App\Contracts;

interface ShoppingAssistantProvider
{
    /**
     * @param array<int, array{role: string, content: string}> $history
     * @param array<string, mixed> $catalogContext
     *
     * @return array{
     *     content: string,
     *     input_tokens: int,
     *     output_tokens: int,
     *     _provider_diagnostics?: array<string, mixed>
     * }
     */
    public function complete(
        string $locale,
        array $history,
        array $catalogContext,
        int $maxOutputTokens
    ): array;
}
