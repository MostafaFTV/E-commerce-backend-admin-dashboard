<?php

namespace Tests\Feature;

use App\Exceptions\ShoppingAssistantUnavailable;
use App\Services\ShoppingAssistant\ShoppingAssistantService;
use App\Services\ShoppingAssistant\ShoppingAssistantUsageLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShoppingAssistantProviderDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'shopping_assistant.enabled' => true,
            'shopping_assistant.driver' => 'gapgpt',
            'shopping_assistant.api_key' => 'offline-test-key',
            'shopping_assistant.base_url' => 'https://api.gapgpt.app/v1',
            'shopping_assistant.endpoint' => 'chat_completions',
            'shopping_assistant.model' => 'gpt-6.1-terra',
            'shopping_assistant.timeout_seconds' => 15,
            'shopping_assistant.max_output_tokens' => 10,
            'shopping_assistant.input_cost_per_million_tokens' => '0.10',
            'shopping_assistant.output_cost_per_million_tokens' => '0.50',
            'shopping_assistant.daily_budget_usd' => '1.00',
            'shopping_assistant.monthly_budget_usd' => '5.00',
        ]);
        Http::preventStrayRequests();
        $this->assertTrue(Schema::hasColumn('shopping_assistant_usage_logs', 'failure_category'));
    }

    public function test_invalid_provider_configuration_is_recorded_before_dispatch_and_releases_only_its_reservation(): void
    {
        config()->set('shopping_assistant.base_url', 'http://invalid.local');
        $unknownReservation = DB::table('shopping_assistant_budget_reservations')->insertGetId([
            'conversation_fingerprint' => hash('sha256', 'historical-unknown'),
            'budget_date' => now()->toDateString(),
            'budget_month' => now()->format('Y-m'),
            'reserved_cost_usd' => '0.000100000000',
            'actual_cost_usd' => null,
            'status' => 'unknown',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postChat()
            ->assertStatus(503)
            ->assertJsonPath(
                'message',
                'The shopping assistant is temporarily unavailable. Please try again later.'
            );

        Http::assertSentCount(0);
        $failure = DB::table('shopping_assistant_usage_logs')->where('status', 'failed')->first();
        $this->assertSame('invalid_provider_configuration', $failure->failure_category);
        $this->assertSame('provider_configuration', $failure->failure_stage);
        $this->assertSame('not_dispatched', $failure->dispatch_state);
        $this->assertNull($failure->provider_http_status);
        $this->assertNotNull($failure->budget_reservation_id);
        $this->assertDatabaseHas('shopping_assistant_budget_reservations', [
            'id' => $failure->budget_reservation_id,
            'status' => 'released',
        ]);
        $this->assertDatabaseHas('shopping_assistant_budget_reservations', [
            'id' => $unknownReservation,
            'status' => 'unknown',
        ]);
    }

    public function test_transport_connection_failure_and_timeout_are_distinguished_and_keep_reservations_unknown(): void
    {
        $cases = [
            [
                'message' => 'cURL error 6: Could not resolve host',
                'category' => 'provider_connection_failure',
                'exception' => ConnectionException::class,
            ],
            [
                'message' => 'cURL error 28: Operation timed out',
                'category' => 'provider_timeout',
                'exception' => ConnectionException::class,
            ],
            [
                'message' => 'simulated transport adapter failure',
                'category' => 'provider_request_error',
                'exception' => \RuntimeException::class,
            ],
        ];
        $attempt = 0;
        Http::fake(function () use (&$attempt, $cases): never {
            $case = $cases[$attempt++];

            if ($case['exception'] === ConnectionException::class) {
                throw new ConnectionException($case['message']);
            }

            throw new \RuntimeException($case['message']);
        });

        foreach ($cases as $case) {
            $this->postChat()->assertStatus(503);

            $failure = DB::table('shopping_assistant_usage_logs')
                ->where('status', 'failed')
                ->latest('id')
                ->first();
            $this->assertSame($case['category'], $failure->failure_category);
            $this->assertSame('provider_transport', $failure->failure_stage);
            $this->assertSame('attempted', $failure->dispatch_state);
            $this->assertNotNull($failure->elapsed_ms);
            $this->assertDatabaseHas('shopping_assistant_budget_reservations', [
                'id' => $failure->budget_reservation_id,
                'status' => 'unknown',
            ]);
        }

        $this->assertSame(3, $attempt);
    }

    public function test_provider_http_rejections_are_categorized_without_recording_response_bodies(): void
    {
        $cases = [
            400 => 'provider_rejected_request',
            401 => 'provider_authentication_failed',
            403 => 'provider_access_forbidden',
            429 => 'provider_rate_limited',
            500 => 'provider_server_error',
            503 => 'provider_server_error',
        ];
        $statuses = array_keys($cases);
        $attempt = 0;
        Http::fake(function () use (&$attempt, $statuses) {
            $status = $statuses[$attempt++];

            return Http::response(
                ['error' => ['message' => 'private provider response body']],
                $status,
                ['x-request-id' => 'req-' . $status]
            );
        });

        foreach ($cases as $status => $category) {
            $this->postChat()
                ->assertStatus(503)
                ->assertJsonMissing(['message' => 'private provider response body']);

            $failure = DB::table('shopping_assistant_usage_logs')
                ->where('status', 'failed')
                ->latest('id')
                ->first();
            $this->assertSame($category, $failure->failure_category);
            $this->assertSame('provider_response', $failure->failure_stage);
            $this->assertSame('response_received', $failure->dispatch_state);
            $this->assertSame($status, (int) $failure->provider_http_status);
            $this->assertSame('req-' . $status, $failure->provider_request_id);
            $this->assertNotNull($failure->elapsed_ms);
            $this->assertDatabaseHas('shopping_assistant_budget_reservations', [
                'id' => $failure->budget_reservation_id,
                'status' => 'unknown',
            ]);
        }

        Http::assertSentCount(6);
        $this->assertDatabaseMissing('shopping_assistant_usage_logs', [
            'failure_category' => 'private provider response body',
        ]);
    }

    public function test_chat_completion_text_parts_are_parsed_without_exposing_provider_payloads(): void
    {
        Http::fake(fn () => Http::response([
            'choices' => [[
                'message' => [
                    'content' => [
                        ['type' => 'text', 'text' => 'The catalog lists the Bosch set.'],
                        ['type' => 'text', 'text' => 'Its induction compatibility is not listed.'],
                    ],
                ],
            ]],
            'usage' => ['prompt_tokens' => 80, 'completion_tokens' => 18],
        ], 200, ['x-request-id' => 'content-parts-1']));

        $this->postChat()
            ->assertOk()
            ->assertJsonPath(
                'data.messages.1.content',
                "The catalog lists the Bosch set.\nIts induction compatibility is not listed."
            )
            ->assertJsonPath('data.usage.input_tokens', 80)
            ->assertJsonPath('data.usage.output_tokens', 18);

        $usage = DB::table('shopping_assistant_usage_logs')->where('status', 'success')->first();
        $this->assertSame('content-parts-1', $usage->provider_request_id);
        Http::assertSentCount(1);
    }

    public function test_refusal_text_and_alternate_usage_fields_are_supported(): void
    {
        Http::fake(fn () => Http::response([
            'choices' => [[
                'message' => [
                    'content' => '',
                    'refusal' => 'I cannot help with that request, but I can help with TKB products.',
                ],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['input_tokens' => 80, 'output_tokens' => 18],
        ], 200, ['x-request-id' => 'refusal-usage-shape']));

        $this->postChat()
            ->assertOk()
            ->assertJsonPath(
                'data.messages.1.content',
                'I cannot help with that request, but I can help with TKB products.'
            )
            ->assertJsonPath('data.usage.input_tokens', 80)
            ->assertJsonPath('data.usage.output_tokens', 18);

        $usage = DB::table('shopping_assistant_usage_logs')->where('status', 'success')->first();
        $this->assertSame('refusal-usage-shape', $usage->provider_request_id);
        Http::assertSentCount(1);
    }

    public function test_response_shape_log_contains_only_allowlisted_metadata(): void
    {
        Log::spy();
        Http::fake(fn () => Http::response([
            'choices' => [[
                'message' => [
                    'content' => [[
                        'type' => 'image',
                        'text' => 'PRIVATE_MODEL_RESPONSE_SENTINEL',
                    ]],
                ],
                'finish_reason' => 'content_filter',
            ]],
            'usage' => ['prompt_tokens' => 80],
            'error' => ['code' => 'PRIVATE PROVIDER ERROR SENTINEL'],
        ], 200, ['x-request-id' => 'safe-shape-1']));

        $this->postChat()->assertStatus(503);

        $failure = DB::table('shopping_assistant_usage_logs')
            ->where('status', 'failed')
            ->first();
        $this->assertSame('provider_content_filtered', $failure->failure_category);
        Log::shouldHaveReceived('log')
            ->with('warning', 'Shopping assistant provider response shape.', \Mockery::on(
                function (array $context): bool {
                    $encoded = json_encode($context) ?: '';

                    return ($context['provider_http_status'] ?? null) === 200
                        && ($context['response_finish_reason'] ?? null) === 'content_filter'
                        && ($context['response_content_exists'] ?? null) === true
                        && ($context['response_content_type'] ?? null) === 'array'
                        && ($context['response_content_part_types'] ?? null) === ['image']
                        && ($context['response_usage_present'] ?? null) === true
                        && ($context['response_usage_fields']['prompt_tokens'] ?? null) === true
                        && ($context['response_usage_fields']['completion_tokens'] ?? null) === false
                        && ! str_contains($encoded, 'PRIVATE_MODEL_RESPONSE_SENTINEL')
                        && ! str_contains($encoded, 'PRIVATE PROVIDER ERROR SENTINEL');
                }
            ));

        Http::assertSentCount(1);
    }

    public function test_responses_endpoint_parses_text_parts_and_records_safe_shape_metadata(): void
    {
        config()->set('shopping_assistant.endpoint', 'responses');
        Http::fake(fn () => Http::response([
            'status' => 'completed',
            'output_text' => '',
            'output' => [[
                'type' => 'message',
                'content' => [
                    ['type' => 'output_text', 'text' => 'The catalog lists the Bosch set.'],
                    ['type' => 'text', 'text' => 'Induction compatibility is not listed.'],
                ],
            ]],
            'usage' => ['input_tokens' => 70, 'output_tokens' => 16],
        ], 200, ['x-request-id' => 'responses-parts-1']));

        $this->postChat()
            ->assertOk()
            ->assertJsonPath(
                'data.messages.1.content',
                "The catalog lists the Bosch set.\nInduction compatibility is not listed."
            )
            ->assertJsonPath('data.usage.input_tokens', 70)
            ->assertJsonPath('data.usage.output_tokens', 16);

        Http::assertSentCount(1);
    }

    public function test_malformed_provider_response_and_missing_usage_are_diagnosed_after_response(): void
    {
        $attempt = 0;
        Http::fake(function () use (&$attempt) {
            $attempt++;

            if ($attempt === 1) {
                return Http::response(['unexpected' => ['shape' => true]], 200, [
                    'request-id' => 'malformed-response-1',
                ]);
            }

            if ($attempt === 2) {
                return Http::response([
                    'choices' => [['message' => ['content' => 'A catalog-oriented reply.']]],
                ], 200, ['x-request-id' => 'missing-usage-2']);
            }

            return Http::response([
                'choices' => [['message' => ['content' => 'A catalog-oriented reply.']]],
                'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 4],
            ], 200, ['x-request-id' => 'zero-usage-3']);
        });

        $this->postChat()->assertStatus(503);
        $malformed = DB::table('shopping_assistant_usage_logs')
            ->where('status', 'failed')
            ->latest('id')
            ->first();
        $this->assertSame('provider_invalid_response', $malformed->failure_category);
        $this->assertSame('response_received', $malformed->dispatch_state);
        $this->assertSame(200, (int) $malformed->provider_http_status);
        $this->assertSame('malformed-response-1', $malformed->provider_request_id);
        $this->assertDatabaseHas('shopping_assistant_budget_reservations', [
            'id' => $malformed->budget_reservation_id,
            'status' => 'unknown',
        ]);

        $this->postChat()->assertStatus(503);
        $missingUsage = DB::table('shopping_assistant_usage_logs')
            ->where('status', 'failed')
            ->latest('id')
            ->first();
        $this->assertSame('provider_usage_unavailable', $missingUsage->failure_category);
        $this->assertSame('provider_usage', $missingUsage->failure_stage);
        $this->assertSame('missing-usage-2', $missingUsage->provider_request_id);
        $this->assertDatabaseHas('shopping_assistant_budget_reservations', [
            'id' => $missingUsage->budget_reservation_id,
            'status' => 'unknown',
        ]);

        $this->postChat()->assertStatus(503);
        $zeroUsage = DB::table('shopping_assistant_usage_logs')
            ->where('status', 'failed')
            ->latest('id')
            ->first();
        $this->assertSame('provider_usage_unavailable', $zeroUsage->failure_category);
        $this->assertSame('zero-usage-3', $zeroUsage->provider_request_id);
        $this->assertDatabaseHas('shopping_assistant_budget_reservations', [
            'id' => $zeroUsage->budget_reservation_id,
            'status' => 'unknown',
        ]);

        Http::assertSentCount(3);
    }

    public function test_empty_and_unsupported_chat_content_have_distinct_safe_categories(): void
    {
        Http::fakeSequence()
            ->push([
                'choices' => [['message' => ['content' => ''], 'finish_reason' => 'length']],
                'usage' => ['prompt_tokens' => 80, 'completion_tokens' => 0],
            ], 200)
            ->push([
                'choices' => [[
                    'message' => [
                        'content' => [['type' => 'image', 'image_url' => 'not-logged']],
                    ],
                ]],
                'usage' => ['prompt_tokens' => 80, 'completion_tokens' => 0],
            ], 200);

        $this->postChat()->assertStatus(503);
        $empty = DB::table('shopping_assistant_usage_logs')
            ->where('status', 'failed')
            ->latest('id')
            ->first();
        $this->assertSame('provider_output_truncated', $empty->failure_category);

        $this->postChat()->assertStatus(503);
        $unsupported = DB::table('shopping_assistant_usage_logs')
            ->where('status', 'failed')
            ->latest('id')
            ->first();
        $this->assertSame('provider_content_format_unsupported', $unsupported->failure_category);
        $this->assertDatabaseMissing('shopping_assistant_usage_logs', [
            'failure_category' => 'not-logged',
        ]);
        Http::assertSentCount(2);
    }

    public function test_unexpected_provider_response_structure_is_a_sanitized_invalid_response(): void
    {
        config()->set('shopping_assistant.endpoint', 'responses');
        Http::fake(fn () => Http::response([
            'output' => 'not-an-array',
        ], 200, ['x-request-id' => 'unexpected-shape-3']));

        $this->postChat()->assertStatus(503);

        $failure = DB::table('shopping_assistant_usage_logs')->where('status', 'failed')->first();
        $this->assertSame('provider_invalid_response', $failure->failure_category);
        $this->assertSame('unexpected-shape-3', $failure->provider_request_id);
        $this->assertDatabaseHas('shopping_assistant_budget_reservations', [
            'id' => $failure->budget_reservation_id,
            'status' => 'unknown',
        ]);
        Http::assertSentCount(1);
    }

    public function test_budget_lock_failure_is_logged_without_dispatching_to_provider(): void
    {
        DB::table('shopping_assistant_budget_locks')->where('id', 1)->delete();

        $this->postChat()->assertStatus(503);

        $failure = DB::table('shopping_assistant_usage_logs')->where('status', 'failed')->first();
        $this->assertSame('budget_lock_unavailable', $failure->failure_category);
        $this->assertSame('budget_reservation', $failure->failure_stage);
        $this->assertSame('not_dispatched', $failure->dispatch_state);
        $this->assertNull($failure->budget_reservation_id);
        Http::assertSentCount(0);
    }

    public function test_settlement_failure_after_provider_success_keeps_the_reservation_unknown_and_linked(): void
    {
        Http::fake(fn () => Http::response([
            'choices' => [['message' => ['content' => 'A product suggestion.']]],
            'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 5],
        ], 200, ['x-request-id' => 'successful-provider-response']));

        $actualLogger = app(ShoppingAssistantUsageLogger::class);
        $call = 0;
        $logger = \Mockery::mock(ShoppingAssistantUsageLogger::class);
        $logger->shouldReceive('record')->twice()->andReturnUsing(
            function (...$arguments) use ($actualLogger, &$call) {
                $call++;
                if ($call === 1) {
                    throw new \RuntimeException('simulated settlement failure');
                }

                return $actualLogger->record(...$arguments);
            }
        );
        $this->app->instance(ShoppingAssistantUsageLogger::class, $logger);

        $this->postChat()->assertStatus(503);

        $failure = DB::table('shopping_assistant_usage_logs')->where('status', 'failed')->first();
        $this->assertSame('usage_settlement_failed', $failure->failure_category);
        $this->assertSame('response_received', $failure->dispatch_state);
        $this->assertSame('successful-provider-response', $failure->provider_request_id);
        $this->assertNotNull($failure->budget_reservation_id);
        $this->assertDatabaseHas('shopping_assistant_budget_reservations', [
            'id' => $failure->budget_reservation_id,
            'status' => 'unknown',
            'actual_cost_usd' => null,
        ]);
        Http::assertSentCount(1);
    }

    public function test_failure_logging_errors_do_not_replace_the_generic_503_or_log_sensitive_input(): void
    {
        $assistant = \Mockery::mock(ShoppingAssistantService::class);
        $assistant->shouldReceive('respond')->once()->andThrow(
            new ShoppingAssistantUnavailable(
                'internal test failure',
                diagnostics: [
                    'failure_stage' => 'provider_configuration',
                    'failure_category' => 'invalid_provider_configuration',
                    'dispatch_state' => 'not_dispatched',
                ]
            )
        );
        $this->app->instance(ShoppingAssistantService::class, $assistant);
        $logger = \Mockery::mock(ShoppingAssistantUsageLogger::class);
        $logger->shouldReceive('record')->once()->andThrow(
            new \RuntimeException('private logger exception text')
        );
        $this->app->instance(ShoppingAssistantUsageLogger::class, $logger);
        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn (string $message, array $context): bool =>
                $message === 'Shopping assistant failure diagnostics could not be persisted.'
                && $context['failure_category'] === 'invalid_provider_configuration'
                && $context['logging_exception_type'] === \RuntimeException::class
                && ! array_key_exists('message', $context)
                && ! array_key_exists('api_key', $context)
            );

        $this->postChat('private customer message')
            ->assertStatus(503)
            ->assertJsonPath(
                'message',
                'The shopping assistant is temporarily unavailable. Please try again later.'
            );

        Http::assertSentCount(0);
    }

    public function test_successful_provider_response_preserves_api_contract_and_sends_one_request(): void
    {
        Http::fake(fn () => Http::response([
            'choices' => [['message' => ['content' => 'A grounded shopping reply.']]],
            'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 5],
        ], 200, ['x-request-id' => 'successful-request-4']));

        $this->postChat()
            ->assertOk()
            ->assertJsonPath('data.messages.1.content', 'A grounded shopping reply.')
            ->assertJsonPath('data.usage.provider', 'gapgpt')
            ->assertJsonPath('data.usage.input_tokens', 12)
            ->assertJsonPath('data.usage.output_tokens', 5)
            ->assertJsonMissingPath('data._provider_diagnostics');

        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool =>
            str_ends_with($request->url(), '/chat/completions')
            && $request['model'] === 'gpt-6.1-terra'
            && $request['max_tokens'] === 10
        );
        $this->assertDatabaseHas('shopping_assistant_usage_logs', [
            'status' => 'success',
            'input_tokens' => 12,
            'output_tokens' => 5,
            'failure_category' => null,
        ]);
    }

    public function test_reported_legacy_503_is_reproduced_and_local_isolated_budget_allows_one_fake_request(): void
    {
        DB::table('shopping_assistant_usage_logs')->insert([
            'id' => 16,
            'conversation_fingerprint' => hash('sha256', 'legacy-unknown-failure'),
            'provider' => 'gapgpt',
            'model' => 'gpt-6.1-terra',
            'input_tokens' => 0,
            'output_tokens' => 0,
            'estimated_cost_usd' => null,
            'status' => 'failed',
            'budget_reservation_id' => null,
            'failure_stage' => null,
            'failure_category' => null,
            'dispatch_state' => null,
            'provider_http_status' => null,
            'elapsed_ms' => null,
            'provider_request_id' => null,
            'created_at' => now(),
        ]);
        DB::table('shopping_assistant_usage_logs')->insert([
            'id' => 21,
            'conversation_fingerprint' => hash('sha256', 'legacy-zero-token-success'),
            'provider' => 'gapgpt',
            'model' => 'gpt-6.1-terra',
            'input_tokens' => 0,
            'output_tokens' => 0,
            'estimated_cost_usd' => null,
            'status' => 'success',
            'budget_reservation_id' => null,
            'failure_stage' => null,
            'failure_category' => null,
            'dispatch_state' => null,
            'provider_http_status' => null,
            'elapsed_ms' => null,
            'provider_request_id' => null,
            'created_at' => now(),
        ]);
        $unknownReservation = DB::table('shopping_assistant_budget_reservations')->insertGetId([
            'conversation_fingerprint' => hash('sha256', 'historical-unknown'),
            'budget_date' => now()->toDateString(),
            'budget_month' => now()->format('Y-m'),
            'reserved_cost_usd' => '0.000578100000',
            'actual_cost_usd' => null,
            'status' => 'unknown',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postChat()->assertStatus(503);
        Http::assertSentCount(0);
        $this->assertDatabaseHas('shopping_assistant_usage_logs', [
            'status' => 'failed',
            'failure_category' => 'legacy_spend_unknown',
            'dispatch_state' => 'not_dispatched',
        ]);

        $this->app->detectEnvironment(static fn () => 'local');
        config()->set('shopping_assistant.local_test_budget_usd', '0.01');
        Http::fake(fn () => Http::response([
            'choices' => [['message' => ['content' => 'A catalog-grounded suggestion.']]],
            'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 5],
        ], 200, ['x-request-id' => 'local-isolated-test']));

        $this->postChat()->assertOk()
            ->assertJsonPath('data.messages.1.content', 'A catalog-grounded suggestion.');

        Http::assertSentCount(1);
        $this->assertDatabaseHas('shopping_assistant_usage_logs', [
            'status' => 'success',
            'provider_request_id' => 'local-isolated-test',
            'input_tokens' => 12,
            'output_tokens' => 5,
        ]);
        $this->assertDatabaseHas('shopping_assistant_budget_reservations', [
            'budget_scope' => 'local_test',
            'status' => 'settled',
        ]);
        $this->assertDatabaseHas('shopping_assistant_budget_reservations', [
            'id' => $unknownReservation,
            'budget_scope' => 'standard',
            'status' => 'unknown',
            'actual_cost_usd' => null,
        ]);
        $this->assertDatabaseHas('shopping_assistant_usage_logs', [
            'id' => 21,
            'status' => 'success',
            'input_tokens' => 0,
            'output_tokens' => 0,
            'estimated_cost_usd' => null,
        ]);
    }

    public function test_locally_filtered_gapgpt_response_is_logged_nonbillable_not_successful(): void
    {
        $this->postChat('Tell me a joke')
            ->assertOk()
            ->assertJsonPath('data.tool', null)
            ->assertJsonPath('data.usage.input_tokens', 0)
            ->assertJsonPath('data.usage.output_tokens', 0)
            ->assertJsonPath('data.usage.estimated_cost_usd', null);

        Http::assertSentCount(0);
        $this->assertDatabaseHas('shopping_assistant_usage_logs', [
            'provider' => 'gapgpt',
            'status' => 'nonbillable',
            'input_tokens' => 0,
            'output_tokens' => 0,
            'estimated_cost_usd' => null,
        ]);
        $this->assertDatabaseMissing('shopping_assistant_usage_logs', [
            'provider' => 'gapgpt',
            'status' => 'success',
            'input_tokens' => 0,
            'output_tokens' => 0,
        ]);
    }

    public function test_gapgpt_usage_logger_rejects_success_without_actual_token_counts(): void
    {
        try {
            app(ShoppingAssistantUsageLogger::class)->record('zero-token-success', 'success');
            $this->fail('A GapGPT success with no reported usage must be rejected.');
        } catch (ShoppingAssistantUnavailable $exception) {
            $this->assertSame('provider_usage_unavailable', $exception->diagnostics()['failure_category']);
        }

        $this->assertDatabaseMissing('shopping_assistant_usage_logs', [
            'conversation_fingerprint' => hash('sha256', 'zero-token-success'),
            'status' => 'success',
        ]);
    }

    private function postChat(string $message = 'Find a kettle')
    {
        return $this->postJson('/api/assistant/chat?locale=en', ['message' => $message]);
    }
}
