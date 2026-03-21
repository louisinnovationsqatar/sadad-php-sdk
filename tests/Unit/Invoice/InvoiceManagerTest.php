<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Tests\Unit\Invoice;

use LouisInnovations\Sadad\Auth\Authenticator;
use LouisInnovations\Sadad\Exceptions\SadadException;
use LouisInnovations\Sadad\Http\HttpClientInterface;
use LouisInnovations\Sadad\Invoice\InvoiceManager;
use LouisInnovations\Sadad\SadadConfig;
use PHPUnit\Framework\TestCase;

class InvoiceManagerTest extends TestCase
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
        );
    }

    private function makeAuthenticator(string $token = 'auth_token'): Authenticator
    {
        $http = new class($token) implements HttpClientInterface {
            public function __construct(private string $token) {}

            public function post(string $url, array $data = [], array $headers = []): array
            {
                return ['accessToken' => $this->token];
            }

            public function get(string $url, array $params = [], array $headers = []): array
            {
                return [];
            }
        };

        return new Authenticator($this->config, $http);
    }

    private function makeHttpClient(
        array $postResponse = [],
        array $getResponse  = [],
        bool $shouldThrow   = false
    ): HttpClientInterface {
        return new class($postResponse, $getResponse, $shouldThrow) implements HttpClientInterface {
            public string $lastPostUrl     = '';
            public array  $lastPostData    = [];
            public array  $lastPostHeaders = [];
            public string $lastGetUrl      = '';
            public array  $lastGetParams   = [];
            public array  $lastGetHeaders  = [];

            public function __construct(
                private array $postResponse,
                private array $getResponse,
                private bool $shouldThrow,
            ) {}

            public function post(string $url, array $data = [], array $headers = []): array
            {
                $this->lastPostUrl     = $url;
                $this->lastPostData    = $data;
                $this->lastPostHeaders = $headers;

                if ($this->shouldThrow) {
                    throw new SadadException('HTTP failed', 'HTTP_ERROR');
                }

                return $this->postResponse;
            }

            public function get(string $url, array $params = [], array $headers = []): array
            {
                $this->lastGetUrl     = $url;
                $this->lastGetParams  = $params;
                $this->lastGetHeaders = $headers;

                if ($this->shouldThrow) {
                    throw new SadadException('HTTP failed', 'HTTP_ERROR');
                }

                return $this->getResponse;
            }
        };
    }

    private function basicInvoiceData(): array
    {
        return [
            'cellnumber'     => '+97477778888',
            'clientname'     => 'John Doe',
            'remarks'        => 'Test invoice',
            'amount'         => 150.00,
            'invoicedetails' => [
                ['description' => 'Product A', 'amount' => 150.00, 'quantity' => 1],
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // createInvoice() - correct endpoint
    // -----------------------------------------------------------------------

    public function testCreateInvoicePostsToCorrectEndpoint(): void
    {
        $http    = $this->makeHttpClient(['invoiceNumber' => 'INV-001', 'invoiceId' => 42]);
        $manager = new InvoiceManager($this->config, $http, $this->makeAuthenticator());

        $manager->createInvoice($this->basicInvoiceData());

        $this->assertSame(
            'https://api-s.sadad.qa/api/invoices/createInvoice',
            $http->lastPostUrl
        );
    }

    // -----------------------------------------------------------------------
    // createInvoice() - payload correctness
    // -----------------------------------------------------------------------

    public function testCreateInvoiceUsesDefaultCountryCode974(): void
    {
        $http    = $this->makeHttpClient(['invoiceNumber' => 'INV-001', 'invoiceId' => 42]);
        $manager = new InvoiceManager($this->config, $http, $this->makeAuthenticator());

        $manager->createInvoice($this->basicInvoiceData());

        $this->assertSame(974, $http->lastPostData['countryCode']);
    }

    public function testCreateInvoiceStripsNonDigitsFromCellnumber(): void
    {
        $http    = $this->makeHttpClient(['invoiceNumber' => 'INV-001', 'invoiceId' => 42]);
        $manager = new InvoiceManager($this->config, $http, $this->makeAuthenticator());

        $manager->createInvoice($this->basicInvoiceData());

        $this->assertSame('97477778888', $http->lastPostData['cellnumber']);
    }

    public function testCreateInvoiceUsesStatusTwoUnpaid(): void
    {
        $http    = $this->makeHttpClient(['invoiceNumber' => 'INV-001', 'invoiceId' => 42]);
        $manager = new InvoiceManager($this->config, $http, $this->makeAuthenticator());

        $manager->createInvoice($this->basicInvoiceData());

        $this->assertSame(2, $http->lastPostData['status']);
    }

    public function testCreateInvoicePassesAmountAsFloat(): void
    {
        $http    = $this->makeHttpClient(['invoiceNumber' => 'INV-001', 'invoiceId' => 42]);
        $manager = new InvoiceManager($this->config, $http, $this->makeAuthenticator());

        $manager->createInvoice($this->basicInvoiceData());

        $this->assertIsFloat($http->lastPostData['amount']);
        $this->assertSame(150.0, $http->lastPostData['amount']);
    }

    public function testCreateInvoicePassesInvoicedetails(): void
    {
        $http    = $this->makeHttpClient(['invoiceNumber' => 'INV-001', 'invoiceId' => 42]);
        $manager = new InvoiceManager($this->config, $http, $this->makeAuthenticator());

        $manager->createInvoice($this->basicInvoiceData());

        $this->assertArrayHasKey('invoicedetails', $http->lastPostData);
        $this->assertCount(1, $http->lastPostData['invoicedetails']);
    }

    // -----------------------------------------------------------------------
    // createInvoice() - authorization header
    // -----------------------------------------------------------------------

    public function testCreateInvoicePassesAuthorizationHeader(): void
    {
        $http    = $this->makeHttpClient(['invoiceNumber' => 'INV-001', 'invoiceId' => 42]);
        $manager = new InvoiceManager($this->config, $http, $this->makeAuthenticator('bearer_xyz'));

        $manager->createInvoice($this->basicInvoiceData());

        $this->assertSame('Bearer bearer_xyz', $http->lastPostHeaders['Authorization']);
    }

    // -----------------------------------------------------------------------
    // createInvoice() - return values
    // -----------------------------------------------------------------------

    public function testCreateInvoiceReturnsInvoiceNumberAndId(): void
    {
        $http    = $this->makeHttpClient(['invoiceNumber' => 'INV-123', 'invoiceId' => 99]);
        $manager = new InvoiceManager($this->config, $http, $this->makeAuthenticator());

        $result = $manager->createInvoice($this->basicInvoiceData());

        $this->assertTrue($result['success']);
        $this->assertSame('INV-123', $result['invoice_number']);
        $this->assertSame(99, $result['invoice_id']);
    }

    public function testCreateInvoiceReturnsErrorOnHttpFailure(): void
    {
        $http    = $this->makeHttpClient([], [], true);
        $manager = new InvoiceManager($this->config, $http, $this->makeAuthenticator());

        $result = $manager->createInvoice($this->basicInvoiceData());

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    // -----------------------------------------------------------------------
    // shareInvoice() - via email
    // -----------------------------------------------------------------------

    public function testShareInvoiceViaEmailPostsToCorrectEndpoint(): void
    {
        $http    = $this->makeHttpClient(['message' => 'Sent']);
        $manager = new InvoiceManager($this->config, $http, $this->makeAuthenticator());

        $manager->shareInvoice('INV-001', 'email', 'customer@example.com');

        $this->assertSame(
            'https://api-s.sadad.qa/api/invoices/share',
            $http->lastPostUrl
        );
    }

    public function testShareInvoiceViaEmailSetsSentvia3(): void
    {
        $http    = $this->makeHttpClient(['message' => 'Sent']);
        $manager = new InvoiceManager($this->config, $http, $this->makeAuthenticator());

        $manager->shareInvoice('INV-001', 'email', 'customer@example.com');

        $this->assertSame(3, $http->lastPostData['sentvia']);
        $this->assertSame('customer@example.com', $http->lastPostData['receiverEmail']);
        $this->assertArrayNotHasKey('receivercellno', $http->lastPostData);
    }

    // -----------------------------------------------------------------------
    // shareInvoice() - via sms
    // -----------------------------------------------------------------------

    public function testShareInvoiceViaSmsSetsSentvia4(): void
    {
        $http    = $this->makeHttpClient(['message' => 'Sent']);
        $manager = new InvoiceManager($this->config, $http, $this->makeAuthenticator());

        $manager->shareInvoice('INV-001', 'sms', '+97477778888');

        $this->assertSame(4, $http->lastPostData['sentvia']);
        $this->assertSame('97477778888', $http->lastPostData['receivercellno']);
        $this->assertArrayNotHasKey('receiverEmail', $http->lastPostData);
    }

    public function testShareInvoiceViaSmsStripsNonDigitsFromRecipient(): void
    {
        $http    = $this->makeHttpClient(['message' => 'Sent']);
        $manager = new InvoiceManager($this->config, $http, $this->makeAuthenticator());

        $manager->shareInvoice('INV-001', 'sms', '+974 7777-8888');

        $this->assertSame('97477778888', $http->lastPostData['receivercellno']);
    }

    public function testShareInvoiceReturnsSuccessWithMessage(): void
    {
        $http    = $this->makeHttpClient(['message' => 'Invoice sent via email']);
        $manager = new InvoiceManager($this->config, $http, $this->makeAuthenticator());

        $result = $manager->shareInvoice('INV-001', 'email', 'test@example.com');

        $this->assertTrue($result['success']);
        $this->assertSame('Invoice sent via email', $result['message']);
    }

    public function testShareInvoiceReturnsErrorOnHttpFailure(): void
    {
        $http    = $this->makeHttpClient([], [], true);
        $manager = new InvoiceManager($this->config, $http, $this->makeAuthenticator());

        $result = $manager->shareInvoice('INV-001', 'email', 'test@example.com');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    // -----------------------------------------------------------------------
    // listInvoices() - correct endpoint and headers
    // -----------------------------------------------------------------------

    public function testListInvoicesGetsFromCorrectEndpoint(): void
    {
        $http    = $this->makeHttpClient([], ['invoices' => []]);
        $manager = new InvoiceManager($this->config, $http, $this->makeAuthenticator());

        $manager->listInvoices();

        $this->assertSame(
            'https://api-s.sadad.qa/api/invoices/listInvoices',
            $http->lastGetUrl
        );
    }

    public function testListInvoicesPassesAuthorizationHeader(): void
    {
        $http    = $this->makeHttpClient([], ['invoices' => []]);
        $manager = new InvoiceManager($this->config, $http, $this->makeAuthenticator('list_token'));

        $manager->listInvoices();

        $this->assertSame('Bearer list_token', $http->lastGetHeaders['Authorization']);
    }

    // -----------------------------------------------------------------------
    // listInvoices() - filter query params
    // -----------------------------------------------------------------------

    public function testListInvoicesPassesFiltersAsQueryParams(): void
    {
        $http    = $this->makeHttpClient([], ['invoices' => []]);
        $manager = new InvoiceManager($this->config, $http, $this->makeAuthenticator());

        $manager->listInvoices([
            'skip'  => 0,
            'limit' => 10,
            'status' => 2,
        ]);

        $this->assertSame(0,  $http->lastGetParams['filter[skip]']);
        $this->assertSame(10, $http->lastGetParams['filter[limit]']);
        $this->assertSame(2,  $http->lastGetParams['filter[status]']);
    }

    public function testListInvoicesNoFiltersProducesEmptyQueryParams(): void
    {
        $http    = $this->makeHttpClient([], ['invoices' => []]);
        $manager = new InvoiceManager($this->config, $http, $this->makeAuthenticator());

        $manager->listInvoices();

        $this->assertEmpty($http->lastGetParams);
    }

    // -----------------------------------------------------------------------
    // listInvoices() - return values
    // -----------------------------------------------------------------------

    public function testListInvoicesReturnsInvoicesArray(): void
    {
        $invoiceList = [
            ['invoiceNumber' => 'INV-001'],
            ['invoiceNumber' => 'INV-002'],
        ];
        $http    = $this->makeHttpClient([], ['invoices' => $invoiceList]);
        $manager = new InvoiceManager($this->config, $http, $this->makeAuthenticator());

        $result = $manager->listInvoices();

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['invoices']);
    }

    public function testListInvoicesReturnsErrorOnHttpFailure(): void
    {
        $http    = $this->makeHttpClient([], [], true);
        $manager = new InvoiceManager($this->config, $http, $this->makeAuthenticator());

        $result = $manager->listInvoices();

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }
}
