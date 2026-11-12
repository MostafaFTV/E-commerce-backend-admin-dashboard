<?php

namespace Tests\Feature;

use App\Services\SmsIrService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class SmsIrServiceTest extends TestCase
{
    private SmsIrService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'smsir.base_url' => 'https://api.sms.ir',
            'smsir.api_key' => 'sandbox-test-key',
            'smsir.verify_template_id' => 123456,
            'smsir.timeout' => 10,
        ]);

        $this->service = $this->app->make(
            SmsIrService::class
        );
    }

    public function test_verify_request_matches_sms_ir_sandbox_contract(): void
    {
        Http::fake([
            'https://api.sms.ir/v1/send/verify' =>
                Http::response([
                    'status' => 1,
                    'message' => 'موفق',
                    'data' => [
                        'messageId' => 89545112,
                        'cost' => 1,
                    ],
                ], 200),
        ]);

        $result = $this->service->sendVerify(
            '989115122045',
            '123456'
        );

        $this->assertSame(
            89545112,
            $result['message_id']
        );

        $this->assertSame(
            1,
            $result['cost']
        );

        Http::assertSent(
            function ($request) {
                return $request->url()
                    === 'https://api.sms.ir/v1/send/verify'
                    && $request->method() === 'POST'
                    && $request->header('x-api-key')[0]
                        === 'sandbox-test-key'
                    && $request->header('Content-Type')[0]
                        === 'application/json'
                    && $request->header('Accept')[0]
                        === 'text/plain'
                    && $request['mobile'] === '989115122045'
                    && $request['templateId'] === 123456
                    && $request['parameters'][0]['name'] === 'Code'
                    && $request['parameters'][0]['value'] === '123456';
            }
        );
    }

    public function test_sandbox_rejection_is_reported_as_exception(): void
    {
        Http::fake([
            'https://api.sms.ir/v1/send/verify' =>
                Http::response([
                    'status' => 0,
                    'message' => 'خطا',
                ], 200),
        ]);

        $this->expectException(
            RuntimeException::class
        );

        $this->service->sendVerify(
            '989115122045',
            '123456'
        );
    }

    public function test_http_failure_is_reported_as_exception(): void
    {
        Http::fake([
            'https://api.sms.ir/v1/send/verify' =>
                Http::response([], 401),
        ]);

        $this->expectException(
            RuntimeException::class
        );

        $this->service->sendVerify(
            '989115122045',
            '123456'
        );
    }

    public function test_missing_api_key_is_rejected(): void
    {
        config([
            'smsir.api_key' => null,
        ]);

        $this->expectException(
            RuntimeException::class
        );

        $this->service->sendVerify(
            '989115122045',
            '123456'
        );
    }

    public function test_default_sandbox_template_is_123456(): void
    {
        config([
            'smsir.verify_template_id' => 123456,
        ]);

        Http::fake([
            'https://api.sms.ir/v1/send/verify' =>
                Http::response([
                    'status' => 1,
                    'message' => 'موفق',
                    'data' => [
                        'messageId' => 1,
                        'cost' => 1,
                    ],
                ], 200),
        ]);

        $this->service->sendVerify(
            '989115122045',
            '123456'
        );

        Http::assertSent(
            function ($request) {
                return $request['templateId'] === 123456;
            }
        );
    }
}