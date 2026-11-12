<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ContactMessageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_message_can_be_submitted(): void
    {
        $response = $this->postJson(
            '/api/contact-messages',
            [
                'full_name' => 'Test Customer',
                'contact' => 'customer@example.com',
                'subject' => 'product',
                'message' => 'I have a product question.',
                'locale' => 'en',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.status',
                'new'
            );

        $this->assertDatabaseHas(
            'contact_messages',
            [
                'full_name' =>
                    'Test Customer',

                'contact' =>
                    'customer@example.com',

                'subject' =>
                    'product',

                'message' =>
                    'I have a product question.',

                'locale' =>
                    'en',

                'status' =>
                    'new',
            ]
        );
    }

    public function test_contact_message_rejects_invalid_subject(): void
    {
        $response = $this->postJson(
            '/api/contact-messages',
            [
                'full_name' => 'Test Customer',
                'contact' => 'customer@example.com',
                'subject' => 'invalid-subject',
                'message' => 'Test message.',
                'locale' => 'en',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'subject',
            ]);

        $this->assertDatabaseCount(
            'contact_messages',
            0
        );
    }

    public function test_contact_message_status_cannot_be_overridden(): void
    {
        $response = $this->postJson(
            '/api/contact-messages',
            [
                'full_name' => 'Test Customer',
                'contact' => 'customer@example.com',
                'subject' => 'other',
                'message' => 'Test message.',
                'locale' => 'fa',
                'status' => 'resolved',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.status',
                'new'
            );

        $this->assertDatabaseHas(
            'contact_messages',
            [
                'full_name' =>
                    'Test Customer',

                'status' =>
                    'new',
            ]
        );

        $this->assertDatabaseMissing(
            'contact_messages',
            [
                'full_name' =>
                    'Test Customer',

                'status' =>
                    'resolved',
            ]
        );
    }

    public function test_contact_message_rate_limits_same_ip_after_five_requests(): void
    {
        $ip =
            '198.51.100.10';

        $ipHash =
            hash(
                'sha256',
                $ip
            );

        $minuteKey =
            'contact-message:ip-minute:' .
            $ipHash;

        $hourKey =
            'contact-message:ip-hour:' .
            $ipHash;

        RateLimiter::clear(
            $minuteKey
        );

        RateLimiter::clear(
            $hourKey
        );

        try {
            for (
                $attempt = 1;
                $attempt <= 5;
                $attempt++
            ) {
                $response = $this
                    ->withServerVariables([
                        'REMOTE_ADDR' =>
                            $ip,
                    ])
                    ->postJson(
                        '/api/contact-messages',
                        [
                            'full_name' =>
                                'IP Limit Test',

                            'contact' =>
                                "ip-limit-{$attempt}@example.com",

                            'subject' =>
                                'other',

                            'message' =>
                                'Rate limit test.',

                            'locale' =>
                                'en',
                        ]
                    );

                $response
                    ->assertCreated();
            }

            $response = $this
                ->withServerVariables([
                    'REMOTE_ADDR' =>
                        $ip,
                ])
                ->postJson(
                    '/api/contact-messages',
                    [
                        'full_name' =>
                            'IP Limit Test',

                        'contact' =>
                            'ip-limit-sixth@example.com',

                        'subject' =>
                            'other',

                        'message' =>
                            'Rate limit test.',

                        'locale' =>
                            'en',
                    ]
                );

            $response
                ->assertStatus(429)
                ->assertJsonPath(
                    'message',
                    'Too many contact requests. Please try again later.'
                );

            $this->assertDatabaseCount(
                'contact_messages',
                5
            );
        } finally {
            RateLimiter::clear(
                $minuteKey
            );

            RateLimiter::clear(
                $hourKey
            );
        }
    }

    public function test_contact_message_rate_limits_same_contact_across_different_ips(): void
    {
        $contact =
            'contact-limit@example.com';

        $contactKey =
            'contact-message:contact-hour:' .
            hash(
                'sha256',
                mb_strtolower(
                    trim(
                        $contact
                    )
                )
            );

        RateLimiter::clear(
            $contactKey
        );

        $ipKeys = [];

        try {
            for (
                $attempt = 1;
                $attempt <= 5;
                $attempt++
            ) {
                $ip =
                    "198.51.100." .
                    (20 + $attempt);

                $ipHash =
                    hash(
                        'sha256',
                        $ip
                    );

                $minuteKey =
                    'contact-message:ip-minute:' .
                    $ipHash;

                $hourKey =
                    'contact-message:ip-hour:' .
                    $ipHash;

                $ipKeys[] = [
                    $minuteKey,
                    $hourKey,
                ];

                RateLimiter::clear(
                    $minuteKey
                );

                RateLimiter::clear(
                    $hourKey
                );

                $response = $this
                    ->withServerVariables([
                        'REMOTE_ADDR' =>
                            $ip,
                    ])
                    ->postJson(
                        '/api/contact-messages',
                        [
                            'full_name' =>
                                'Contact Limit Test',

                            'contact' =>
                                $contact,

                            'subject' =>
                                'other',

                            'message' =>
                                'Rate limit test.',

                            'locale' =>
                                'en',
                        ]
                    );

                $response
                    ->assertCreated();
            }

            $sixthIp =
                '198.51.100.26';

            $sixthHash =
                hash(
                    'sha256',
                    $sixthIp
                );

            $sixthMinuteKey =
                'contact-message:ip-minute:' .
                $sixthHash;

            $sixthHourKey =
                'contact-message:ip-hour:' .
                $sixthHash;

            $ipKeys[] = [
                $sixthMinuteKey,
                $sixthHourKey,
            ];

            RateLimiter::clear(
                $sixthMinuteKey
            );

            RateLimiter::clear(
                $sixthHourKey
            );

            $response = $this
                ->withServerVariables([
                    'REMOTE_ADDR' =>
                        $sixthIp,
                ])
                ->postJson(
                    '/api/contact-messages',
                    [
                        'full_name' =>
                            'Contact Limit Test',

                        'contact' =>
                            $contact,

                        'subject' =>
                            'other',

                        'message' =>
                            'Rate limit test.',

                        'locale' =>
                            'en',
                    ]
                );

            $response
                ->assertStatus(429)
                ->assertJsonPath(
                    'message',
                    'Too many contact requests. Please try again later.'
                );

            $this->assertDatabaseCount(
                'contact_messages',
                5
            );
        } finally {
            RateLimiter::clear(
                $contactKey
            );

            foreach (
                $ipKeys as
                [$minuteKey, $hourKey]
            ) {
                RateLimiter::clear(
                    $minuteKey
                );

                RateLimiter::clear(
                    $hourKey
                );
            }
        }
    }}