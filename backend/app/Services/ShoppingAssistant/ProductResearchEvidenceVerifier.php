<?php

namespace App\Services\ShoppingAssistant;

final class ProductResearchEvidenceVerifier
{
    /**
     * Classify research evidence without approving technical facts.
     *
     * @param array<string, mixed> $research
     * @return array<string, mixed>
     */
    public function assess(
        string $manufacturer,
        string $model,
        array $research
    ): array {
        $manufacturer = trim($manufacturer);
        $model = trim($model);

        $answer = $research['answer'] ?? '';
        $urls = $research['citation_urls'] ?? [];

        if (
            $manufacturer === '' ||
            $model === '' ||
            ! is_string($answer) ||
            ! is_array($urls) ||
            ($research['verification_status'] ?? null) !== 'unverified'
        ) {
            return $this->result('insufficient_evidence', [], [
                'Invalid or missing research evidence.',
            ]);
        }

        $validUrls = [];

        foreach ($urls as $url) {
            if (! is_string($url)) {
                continue;
            }

            $parts = parse_url($url);

            if (
                ! is_array($parts) ||
                strtolower((string) ($parts['scheme'] ?? '')) !== 'https' ||
                ! isset($parts['host']) ||
                isset($parts['user']) ||
                isset($parts['pass'])
            ) {
                continue;
            }

            $host = strtolower($parts['host']);

            if (
                ! str_contains($host, '.') ||
                str_ends_with($host, '.local') ||
                str_ends_with($host, '.internal') ||
                filter_var($host, FILTER_VALIDATE_IP) !== false
            ) {
                continue;
            }

            $validUrls[$url] = $url;
        }

        $validUrls = array_values($validUrls);

        if ($validUrls === []) {
            return $this->result('insufficient_evidence', [], [
                'No valid HTTPS citations.',
            ]);
        }

        $answerHasManufacturer = $this->containsIdentifier(
            $answer,
            $manufacturer
        );

        $answerHasExactModel = $this->containsIdentifier(
            $answer,
            $model
        );

        $modelInSource = false;

        foreach ($validUrls as $url) {
            $path = (string) parse_url($url, PHP_URL_PATH);
            $decodedPath = rawurldecode($path);

            if ($this->containsIdentifier($decodedPath, $model)) {
                $modelInSource = true;
                break;
            }
        }

        if (! $answerHasManufacturer || ! $answerHasExactModel) {
            return $this->result('needs_review', $validUrls, [
                'Exact manufacturer and model were not both found in the answer.',
            ]);
        }

        if (! $modelInSource) {
            return $this->result('needs_review', $validUrls, [
                'Exact model identifier was not found in a cited URL path.',
            ]);
        }

        return $this->result('candidate_exact_model', $validUrls, [
            'Identifier checks passed; source authenticity and individual facts remain unverified.',
        ]);
    }

    private function containsIdentifier(string $text, string $identifier): bool
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return false;
        }

        $pattern = '/(?<![\p{L}\p{N}])'
            . preg_quote($identifier, '/')
            . '(?![\p{L}\p{N}])/iu';

        return preg_match($pattern, $text) === 1;
    }

    /**
     * @param array<int, string> $urls
     * @param array<int, string> $reasons
     * @return array<string, mixed>
     */
    private function result(
        string $status,
        array $urls,
        array $reasons
    ): array {
        return [
            'status' => $status,
            'approved' => false,
            'citation_urls' => $urls,
            'reasons' => $reasons,
        ];
    }
}