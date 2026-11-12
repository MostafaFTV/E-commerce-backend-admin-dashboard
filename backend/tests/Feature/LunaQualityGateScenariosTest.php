<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ShoppingAssistant\ShoppingAssistantConversationRouter;
use App\Services\ShoppingAssistant\ShoppingAssistantConsultationMemory;
use App\Services\ShoppingAssistant\ShoppingAssistantHumanHandoff;
use App\Services\ShoppingAssistant\ShoppingAssistantResponseContract;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Offline deterministic quality gate. One data row = one independently named
 * PHPUnit test. Not a substitute for evaluating GPT language quality.
 */
final class LunaQualityGateScenariosTest extends TestCase
{
    /** @return iterable<string, array{0:array<string,mixed>}> */
    public static function scenarios(): iterable
    {
        $path = __DIR__ . '/Fixtures/LunaQualityScenarios.json';
        $json = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        foreach ($json['cases'] as $case) {
            yield $case['id'] => [$case];
        }
    }

    #[DataProvider('scenarios')]
    public function test_contract_scenario(array $case): void
    {
        $input = $case['input'];
        $expected = $case['expected'];
        switch ($case['category']) {
            case 'option':
                $this->assertSame($expected, ShoppingAssistantConversationRouter::optionIndex($input));
                return;
            case 'page':
                $this->assertSame($expected, ShoppingAssistantConversationRouter::page($input)['path'] ?? null);
                return;
            case 'compare':
                $this->assertSame($expected, ShoppingAssistantConversationRouter::comparing($input));
                return;
            case 'handoff':
                $this->assertSame($expected, ShoppingAssistantHumanHandoff::requested($input));
                return;
            case 'response':
                $parsed = ShoppingAssistantResponseContract::parse($input);
                $this->assertSame($expected, $parsed !== null);
                return;
            case 'memory':
                $observed = ShoppingAssistantConsultationMemory::observe([], $input, 'fa');
                $this->assertSame($expected['value'], $observed['preferences'][$expected['key']] ?? null);
                return;
            default:
                $this->fail('Unknown quality category: ' . $case['category']);
        }
    }
}
