<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Tests\Unit\Transaction;

use LouisInnovations\Sadad\Auth\Authenticator;
use LouisInnovations\Sadad\Exceptions\SadadException;
use LouisInnovations\Sadad\Http\HttpClientInterface;
use LouisInnovations\Sadad\SadadConfig;
use LouisInnovations\Sadad\Transaction\TransactionManager;
use PHPUnit\Framework\TestCase;

class TransactionManagerTest extends TestCase
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

    private function makeAuthenticator(string $token = 'test_token'): Authenticator
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

    private function makeHttpClient(array $response, bool $shouldThrow = false): HttpClientInterface
    {
        return new class($response, $shouldThrow) implements HttpClientInterface {
            public string $lastGetUrl     = '';
            public array  $lastGetParams  = [];
            public array  $lastGetHeaders = [];

            public function __construct(
                private array $response,
                private bool $shouldThrow,
            ) {}

            public function post(string $url, array $data = [], array $headers = []): array
            {
                return [];
            }

            public function get(string $url, array $params = [], array $headers = []): array
            {
                $this->lastGetUrl     = $url;
                $this->lastGetParams  = $params;
                $this->lastGetHeaders = $headers;

                if ($this->shouldThrow) {
                    throw new SadadException('HTTP GET failed', 'HTTP_ERROR');
                }

                return $this->response;
            }
        };
    }

    // -----------------------------------------------------------------------
    // getTransaction() - correct endpoint
    // -----------------------------------------------------------------------

    public function testGetTransactionCallsCorrectEndpoint(): void
    {
        $http    = $this->makeHttpClient(['transactionno' => 'TXN-001', 'status' => 3]);
        $manager = new TransactionManager($this->config, $http, $this->makeAuthenticator());

        $manager->getTransaction('TXN-001');

        $this->assertSame(
            'https://api-s.sadad.qa/api/transactions/getTransaction',
            $http->lastGetUrl
        );
    }

    public function testGetTransactionPassesTransactionNumberAsQueryParam(): void
    {
        $http    = $this->makeHttpClient(['transactionno' => 'TXN-001', 'status' => 3]);
        $manager = new TransactionManager($this->config, $http, $this->makeAuthenticator());

        $manager->getTransaction('TXN-001');

        $this->assertSame('TXN-001', $http->lastGetParams['transactionno']);
    }

    // -----------------------------------------------------------------------
    // getTransaction() - passes Authorization header
    // -----------------------------------------------------------------------

    public function testGetTransactionPassesAuthHeader(): void
    {
        $http    = $this->makeHttpClient(['transactionno' => 'TXN-001', 'status' => 3]);
        $manager = new TransactionManager($this->config, $http, $this->makeAuthenticator('my_bearer_token'));

        $manager->getTransaction('TXN-001');

        $this->assertSame('Bearer my_bearer_token', $http->lastGetHeaders['Authorization']);
    }

    // -----------------------------------------------------------------------
    // getTransaction() - returns parsed response
    // -----------------------------------------------------------------------

    public function testGetTransactionReturnsSuccessWithTransactionData(): void
    {
        $transactionData = [
            'transactionno' => 'TXN-001',
            'status'        => 3,
            'amount'        => 100.00,
        ];
        $http    = $this->makeHttpClient($transactionData);
        $manager = new TransactionManager($this->config, $http, $this->makeAuthenticator());

        $result = $manager->getTransaction('TXN-001');

        $this->assertTrue($result['success']);
        $this->assertSame($transactionData, $result['transaction']);
    }

    // -----------------------------------------------------------------------
    // getTransaction() - handles failure gracefully
    // -----------------------------------------------------------------------

    public function testGetTransactionReturnsErrorOnHttpFailure(): void
    {
        $http    = $this->makeHttpClient([], true);
        $manager = new TransactionManager($this->config, $http, $this->makeAuthenticator());

        $result = $manager->getTransaction('TXN-001');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('HTTP GET failed', $result['error']);
    }
}
