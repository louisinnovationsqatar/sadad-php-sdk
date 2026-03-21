<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Tests\Unit;

use InvalidArgumentException;
use LouisInnovations\Sadad\Callback\CallbackResult;
use LouisInnovations\Sadad\Checkout\CheckoutResult;
use LouisInnovations\Sadad\Exceptions\SadadException;
use LouisInnovations\Sadad\Http\HttpClientInterface;
use LouisInnovations\Sadad\SadadClient;
use LouisInnovations\Sadad\SadadConfig;
use LouisInnovations\Sadad\Signature\SignatureV1;
use LouisInnovations\Sadad\Webhook\WebhookResult;
use PHPUnit\Framework\TestCase;

class SadadClientTest extends TestCase
{
    private SadadConfig $config;

    protected function setUp(): void
    {
        $this->config = new SadadConfig(
            merchantId:  '7015085',
            secretKey:   'T1ds45#sGQbodf5',
            website:     'www.example.com',
            environment: 'test',
            language:    'eng',
            callbackUrl: 'https://www.example.com/callback',
        );
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeHttpClient(array $response = []): HttpClientInterface
    {
        return new class($response) implements HttpClientInterface {
            public function __construct(private array $response) {}

            public function post(string $url, array $data = [], array $headers = []): array
            {
                if ($this->response === ['__throw__' => true]) {
                    throw new SadadException('HTTP failure', 'HTTP_ERROR');
                }
                return $this->response;
            }

            public function get(string $url, array $params = [], array $headers = []): array
            {
                if ($this->response === ['__throw__' => true]) {
                    throw new SadadException('HTTP failure', 'HTTP_ERROR');
                }
                return $this->response;
            }
        };
    }

    private function singleItemOrder(): array
    {
        return [
            'order_id' => 'ORD-001',
            'amount'   => 100.00,
            'mobile'   => '97477778888',
            'email'    => 'customer@example.com',
            'items'    => [
                ['order_id' => 'ORD-001', 'amount' => 100.00, 'quantity' => 1],
            ],
        ];
    }

    private function buildValidWebhookPayload(): array
    {
        $data = [
            'merchant_id'        => '7015085',
            'ORDER_ID'           => 'ORD-001',
            'TXN_AMOUNT'         => '100.00',
            'transactionStatus'  => 3,
            'transaction_number' => 'TXN-12345',
            'message'            => 'Transaction successful',
            'isTestMode'         => true,
        ];
        $hash = SignatureV1::generate($data, $this->config->secretKey);
        $data['checksumhash'] = $hash;
        return $data;
    }

    private function buildValidV1CallbackPayload(): array
    {
        $data = [
            'ORDERID'            => 'ORD-001',
            'transaction_number' => 'TXN-12345',
            'TXNAMOUNT'          => '100.00',
            'RESPCODE'           => '1',
            'RESPMSG'            => 'Transaction successful',
            'STATUS'             => 'TXN_SUCCESS',
        ];
        $hash = SignatureV1::generate($data, $this->config->secretKey);
        $data['checksumhash'] = $hash;
        return $data;
    }

    // -----------------------------------------------------------------------
    // Constructor
    // -----------------------------------------------------------------------

    public function testConstructsWithoutErrors(): void
    {
        $client = new SadadClient($this->config, $this->makeHttpClient());
        $this->assertInstanceOf(SadadClient::class, $client);
    }

    public function testConstructsWithDefaultHttpClient(): void
    {
        // Should not throw — GuzzleHttpClient is used by default
        $client = new SadadClient($this->config);
        $this->assertInstanceOf(SadadClient::class, $client);
    }

    public function testConstructsWithCustomHttpClient(): void
    {
        $http   = $this->makeHttpClient();
        $client = new SadadClient($this->config, $http);
        $this->assertInstanceOf(SadadClient::class, $client);
    }

    // -----------------------------------------------------------------------
    // checkout() — returns CheckoutResult for each version
    // -----------------------------------------------------------------------

    public function testCheckoutV11ReturnsCheckoutResult(): void
    {
        $client = new SadadClient($this->config, $this->makeHttpClient());
        $result = $client->checkout($this->singleItemOrder(), 'v1.1');

        $this->assertInstanceOf(CheckoutResult::class, $result);
    }

    public function testCheckoutV11HasCorrectUrl(): void
    {
        $client = new SadadClient($this->config, $this->makeHttpClient());
        $result = $client->checkout($this->singleItemOrder(), 'v1.1');

        $this->assertSame('https://sadadqa.com/webpurchase', $result->url);
    }

    public function testCheckoutV21ReturnsCheckoutResult(): void
    {
        $client = new SadadClient($this->config, $this->makeHttpClient());
        $result = $client->checkout($this->singleItemOrder(), 'v2.1');

        $this->assertInstanceOf(CheckoutResult::class, $result);
    }

    public function testCheckoutV21HasCorrectUrl(): void
    {
        $client = new SadadClient($this->config, $this->makeHttpClient());
        $result = $client->checkout($this->singleItemOrder(), 'v2.1');

        $this->assertSame('https://sadadqa.com/webpurchase', $result->url);
    }

    public function testCheckoutV22ReturnsCheckoutResult(): void
    {
        $client = new SadadClient($this->config, $this->makeHttpClient());
        $result = $client->checkout($this->singleItemOrder(), 'v2.2');

        $this->assertInstanceOf(CheckoutResult::class, $result);
    }

    public function testCheckoutV22HasCorrectUrl(): void
    {
        $client = new SadadClient($this->config, $this->makeHttpClient());
        $result = $client->checkout($this->singleItemOrder(), 'v2.2');

        $this->assertSame('https://secure.sadadqa.com/webpurchasepage', $result->url);
    }

    public function testCheckoutDefaultVersionIsV11(): void
    {
        $client = new SadadClient($this->config, $this->makeHttpClient());
        $result = $client->checkout($this->singleItemOrder());

        $this->assertInstanceOf(CheckoutResult::class, $result);
        $this->assertSame('https://sadadqa.com/webpurchase', $result->url);
    }

    public function testCheckoutThrowsOnInvalidVersion(): void
    {
        $client = new SadadClient($this->config, $this->makeHttpClient());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid checkout version: v99.9');

        $client->checkout($this->singleItemOrder(), 'v99.9');
    }

    public function testCheckoutThrowsOnEmptyVersion(): void
    {
        $client = new SadadClient($this->config, $this->makeHttpClient());

        $this->expectException(InvalidArgumentException::class);

        $client->checkout($this->singleItemOrder(), '');
    }

    // -----------------------------------------------------------------------
    // handleWebhook()
    // -----------------------------------------------------------------------

    public function testHandleWebhookReturnsWebhookResult(): void
    {
        $client  = new SadadClient($this->config, $this->makeHttpClient());
        $payload = $this->buildValidWebhookPayload();
        $result  = $client->handleWebhook($payload);

        $this->assertInstanceOf(WebhookResult::class, $result);
    }

    public function testHandleWebhookSuccessfulPayload(): void
    {
        $client  = new SadadClient($this->config, $this->makeHttpClient());
        $payload = $this->buildValidWebhookPayload();
        $result  = $client->handleWebhook($payload);

        $this->assertTrue($result->isSuccess);
        $this->assertSame('TXN-12345', $result->transactionNumber);
        $this->assertSame('ORD-001', $result->orderNumber);
    }

    // -----------------------------------------------------------------------
    // handleCallback()
    // -----------------------------------------------------------------------

    public function testHandleCallbackReturnsCallbackResult(): void
    {
        $client  = new SadadClient($this->config, $this->makeHttpClient());
        $payload = $this->buildValidV1CallbackPayload();
        $result  = $client->handleCallback($payload, 'v1.1');

        $this->assertInstanceOf(CallbackResult::class, $result);
    }

    public function testHandleCallbackDefaultVersionIsV11(): void
    {
        $client  = new SadadClient($this->config, $this->makeHttpClient());
        $payload = $this->buildValidV1CallbackPayload();
        $result  = $client->handleCallback($payload);

        $this->assertInstanceOf(CallbackResult::class, $result);
    }

    public function testHandleCallbackSuccessfulPayload(): void
    {
        $client  = new SadadClient($this->config, $this->makeHttpClient());
        $payload = $this->buildValidV1CallbackPayload();
        $result  = $client->handleCallback($payload, 'v1.1');

        $this->assertTrue($result->isSuccess);
        $this->assertSame('ORD-001', $result->orderNumber);
    }

    // -----------------------------------------------------------------------
    // webhookSuccessResponse()
    // -----------------------------------------------------------------------

    public function testWebhookSuccessResponseReturnsCorrectArray(): void
    {
        $response = SadadClient::webhookSuccessResponse();

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
        $this->assertSame('success', $response['status']);
    }

    public function testWebhookSuccessResponseHasExactlyOneKey(): void
    {
        $response = SadadClient::webhookSuccessResponse();

        $this->assertCount(1, $response);
    }

    public function testWebhookSuccessResponseIsCallableStatically(): void
    {
        $this->assertIsArray(SadadClient::webhookSuccessResponse());
    }

    // -----------------------------------------------------------------------
    // createInvoice()
    // -----------------------------------------------------------------------

    public function testCreateInvoiceMethodExists(): void
    {
        $client = new SadadClient($this->config, $this->makeHttpClient(['accessToken' => 'tok', 'invoiceNumber' => 'INV-001']));
        $this->assertTrue(method_exists($client, 'createInvoice'));
    }

    public function testCreateInvoiceReturnsArray(): void
    {
        $http   = $this->makeHttpClient(['accessToken' => 'tok', 'invoiceNumber' => 'INV-001']);
        $client = new SadadClient($this->config, $http);

        $result = $client->createInvoice([
            'cellnumber'     => '97477778888',
            'clientname'     => 'Test Customer',
            'remarks'        => 'Test invoice',
            'amount'         => 100.00,
            'invoicedetails' => [],
        ]);

        $this->assertIsArray($result);
    }

    // -----------------------------------------------------------------------
    // shareInvoice()
    // -----------------------------------------------------------------------

    public function testShareInvoiceMethodExists(): void
    {
        $client = new SadadClient($this->config, $this->makeHttpClient());
        $this->assertTrue(method_exists($client, 'shareInvoice'));
    }

    public function testShareInvoiceReturnsArray(): void
    {
        $http   = $this->makeHttpClient(['accessToken' => 'tok', 'message' => 'Sent']);
        $client = new SadadClient($this->config, $http);

        $result = $client->shareInvoice('INV-001', 'email', 'test@example.com');

        $this->assertIsArray($result);
    }

    // -----------------------------------------------------------------------
    // listInvoices()
    // -----------------------------------------------------------------------

    public function testListInvoicesMethodExists(): void
    {
        $client = new SadadClient($this->config, $this->makeHttpClient());
        $this->assertTrue(method_exists($client, 'listInvoices'));
    }

    public function testListInvoicesReturnsArray(): void
    {
        $http   = $this->makeHttpClient(['accessToken' => 'tok', 'invoices' => []]);
        $client = new SadadClient($this->config, $http);

        $result = $client->listInvoices();

        $this->assertIsArray($result);
    }

    // -----------------------------------------------------------------------
    // refund()
    // -----------------------------------------------------------------------

    public function testRefundMethodExists(): void
    {
        $client = new SadadClient($this->config, $this->makeHttpClient());
        $this->assertTrue(method_exists($client, 'refund'));
    }

    // -----------------------------------------------------------------------
    // getTransaction()
    // -----------------------------------------------------------------------

    public function testGetTransactionMethodExists(): void
    {
        $client = new SadadClient($this->config, $this->makeHttpClient());
        $this->assertTrue(method_exists($client, 'getTransaction'));
    }

    public function testGetTransactionReturnsArray(): void
    {
        $http   = $this->makeHttpClient(['accessToken' => 'tok', 'status' => 3]);
        $client = new SadadClient($this->config, $http);

        $result = $client->getTransaction('TXN-12345');

        $this->assertIsArray($result);
    }

    // -----------------------------------------------------------------------
    // All delegate methods are callable
    // -----------------------------------------------------------------------

    public function testAllPublicMethodsAreCallable(): void
    {
        $client = new SadadClient($this->config, $this->makeHttpClient());

        $this->assertTrue(method_exists($client, 'checkout'));
        $this->assertTrue(method_exists($client, 'handleWebhook'));
        $this->assertTrue(method_exists($client, 'handleCallback'));
        $this->assertTrue(method_exists($client, 'createInvoice'));
        $this->assertTrue(method_exists($client, 'shareInvoice'));
        $this->assertTrue(method_exists($client, 'listInvoices'));
        $this->assertTrue(method_exists($client, 'refund'));
        $this->assertTrue(method_exists($client, 'getTransaction'));
        $this->assertTrue(method_exists(SadadClient::class, 'webhookSuccessResponse'));
    }
}
