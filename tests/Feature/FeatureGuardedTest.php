<?php

namespace MugiWara\FeatureFlags\Tests\Feature;

use MugiWara\FeatureFlags\Concerns\FeatureGuarded;
use MugiWara\FeatureFlags\Exceptions\FeatureDisabledException;
use MugiWara\FeatureFlags\Facades\Feature;
use MugiWara\FeatureFlags\Tests\TestCase;

// Minimal service that uses the trait
class PaymentService
{
    use FeatureGuarded;

    public function process(): string
    {
        $this->requireFeature('payments');
        return 'processed';
    }

    public function sendReceipt(): ?string
    {
        return $this->whenFeature('email_receipts', fn() => 'receipt_sent');
    }

    public function refund(): string
    {
        return $this->executeIfFeature('payments', fn() => 'refunded');
    }
}

class FeatureGuardedTest extends TestCase
{
    private PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PaymentService();
    }

    // -----------------------------------------------------------------------
    // requireFeature
    // -----------------------------------------------------------------------

    public function test_require_feature_does_not_throw_when_enabled(): void
    {
        Feature::enable('payments');

        $result = $this->service->process();

        $this->assertSame('processed', $result);
    }

    public function test_require_feature_throws_when_disabled(): void
    {
        Feature::disable('payments');

        $this->expectException(FeatureDisabledException::class);

        $this->service->process();
    }

    public function test_exception_message_includes_feature_name(): void
    {
        Feature::disable('payments');

        try {
            $this->service->process();
            $this->fail('Expected FeatureDisabledException');
        } catch (FeatureDisabledException $e) {
            $this->assertStringContainsString('payments', $e->getMessage());
        }
    }

    // -----------------------------------------------------------------------
    // whenFeature
    // -----------------------------------------------------------------------

    public function test_when_feature_executes_callback_when_enabled(): void
    {
        Feature::enable('email_receipts');

        $result = $this->service->sendReceipt();

        $this->assertSame('receipt_sent', $result);
    }

    public function test_when_feature_returns_null_when_disabled(): void
    {
        Feature::disable('email_receipts');

        $result = $this->service->sendReceipt();

        $this->assertNull($result);
    }

    // -----------------------------------------------------------------------
    // executeIfFeature
    // -----------------------------------------------------------------------

    public function test_execute_if_feature_runs_callback_when_enabled(): void
    {
        Feature::enable('payments');

        $result = $this->service->refund();

        $this->assertSame('refunded', $result);
    }

    public function test_execute_if_feature_throws_when_disabled(): void
    {
        Feature::disable('payments');

        $this->expectException(FeatureDisabledException::class);

        $this->service->refund();
    }
}
