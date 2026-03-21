<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Tests\Unit\Webhook;

use LouisInnovations\Sadad\Exceptions\SignatureException;
use LouisInnovations\Sadad\SadadConfig;
use LouisInnovations\Sadad\Signature\SignatureV1;
use LouisInnovations\Sadad\Webhook\WebhookHandler;
use LouisInnovations\Sadad\Webhook\WebhookResult;
use PHPUnit\Framework\TestCase;

class WebhookHandlerTest extends TestCase
{
    private SadadConfig $config;

    protected function setUp(): void
    {
        $this->config = new SadadConfig(
            merchantId:  '7015085',
            secretKey:   'T1ds45#sGQbodf5',
            website:     'www.example.com',
            environment: 'test',
        );
    }

    /**
     * Build a webhook payload and compute its checksumhash via SignatureV1.
     *
     * @param  array<string, mixed> $payload  Fields without checksumhash.
     * @param  string               $secretKey
     * @return array<string, mixed>
     */
    private function buildValidPayload(array $payload, string $secretKey): array
    {
        $hash                  = SignatureV1::generate($payload, $secretKey);
        $payload['checksumhash'] = $hash;
        return $payload;
    }

    private function defaultPayload(): array
    {
        return [
            'merchant_id'        => '7015085',
            'ORDER_ID'           => 'ORD-WH-001',
            'TXN_AMOUNT'         => '150.00',
            'transactionStatus'  => 3,
            'transaction_number' => 'TXN-ABC-9876',
            'message'            => 'Transaction successful',
            'isTestMode'         => true,
        ];
    }

    // -----------------------------------------------------------------------
    // Valid payload
    // -----------------------------------------------------------------------

    public function testValidWebhookReturnsWebhookResult(): void
    {
        $payload = $this->buildValidPayload($this->defaultPayload(), $this->config->secretKey);
        $handler = new WebhookHandler($this->config);
        $result  = $handler->handle($payload);

        $this->assertInstanceOf(WebhookResult::class, $result);
    }

    public function testValidWebhookParsesAllFields(): void
    {
        $payload = $this->buildValidPayload($this->defaultPayload(), $this->config->secretKey);
        $handler = new WebhookHandler($this->config);
        $result  = $handler->handle($payload);

        $this->assertTrue($result->isSuccess);
        $this->assertSame('Transaction successful', $result->message);
        $this->assertSame('TXN-ABC-9876', $result->transactionNumber);
        $this->assertSame('ORD-WH-001', $result->orderNumber);
        $this->assertSame(150.00, $result->amount);
        $this->assertSame('7015085', $result->merchantId);
        $this->assertTrue($result->isTestMode);
        $this->assertNull($result->invoiceNumber);
    }

    // -----------------------------------------------------------------------
    // isSuccess logic
    // -----------------------------------------------------------------------

    public function testTransactionStatus3MeansSuccess(): void
    {
        $base    = array_merge($this->defaultPayload(), ['transactionStatus' => 3]);
        $payload = $this->buildValidPayload($base, $this->config->secretKey);
        $handler = new WebhookHandler($this->config);

        $this->assertTrue($handler->handle($payload)->isSuccess);
    }

    public function testTransactionStatusOtherThan3MeansFailure(): void
    {
        foreach ([0, 1, 2, 4, 99] as $status) {
            $base    = array_merge($this->defaultPayload(), ['transactionStatus' => $status]);
            $payload = $this->buildValidPayload($base, $this->config->secretKey);
            $handler = new WebhookHandler($this->config);

            $this->assertFalse($handler->handle($payload)->isSuccess, "Expected isSuccess=false for transactionStatus=$status");
        }
    }

    // -----------------------------------------------------------------------
    // Optional invoiceNumber
    // -----------------------------------------------------------------------

    public function testInvoiceNumberIsParsedWhenPresent(): void
    {
        $base    = array_merge($this->defaultPayload(), ['invoiceNumber' => 'INV-2024-001']);
        $payload = $this->buildValidPayload($base, $this->config->secretKey);
        $handler = new WebhookHandler($this->config);
        $result  = $handler->handle($payload);

        $this->assertSame('INV-2024-001', $result->invoiceNumber);
    }

    public function testInvoiceNumberIsNullWhenAbsent(): void
    {
        $payload = $this->buildValidPayload($this->defaultPayload(), $this->config->secretKey);
        $handler = new WebhookHandler($this->config);
        $result  = $handler->handle($payload);

        $this->assertNull($result->invoiceNumber);
    }

    // -----------------------------------------------------------------------
    // Tampered payload
    // -----------------------------------------------------------------------

    public function testTamperedPayloadThrowsSignatureException(): void
    {
        $payload = $this->buildValidPayload($this->defaultPayload(), $this->config->secretKey);

        // Tamper with amount after signing
        $payload['TXN_AMOUNT'] = '9999.99';

        $this->expectException(SignatureException::class);
        (new WebhookHandler($this->config))->handle($payload);
    }

    public function testTamperedChecksumThrowsSignatureException(): void
    {
        $payload = $this->buildValidPayload($this->defaultPayload(), $this->config->secretKey);
        $payload['checksumhash'] = str_repeat('a', 64);

        $this->expectException(SignatureException::class);
        (new WebhookHandler($this->config))->handle($payload);
    }

    public function testMissingChecksumThrowsSignatureException(): void
    {
        $payload = $this->defaultPayload();  // No checksumhash at all

        $this->expectException(SignatureException::class);
        (new WebhookHandler($this->config))->handle($payload);
    }

    // -----------------------------------------------------------------------
    // successResponse
    // -----------------------------------------------------------------------

    public function testSuccessResponseReturnsCorrectArray(): void
    {
        $response = WebhookHandler::successResponse();

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
        $this->assertSame('success', $response['status']);
    }

    public function testSuccessResponseHasExactlyOneKey(): void
    {
        $response = WebhookHandler::successResponse();

        $this->assertCount(1, $response);
    }
}
