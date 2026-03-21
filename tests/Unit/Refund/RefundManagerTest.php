<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Tests\Unit\Refund;

use LouisInnovations\Sadad\Auth\Authenticator;
use LouisInnovations\Sadad\Exceptions\RefundException;
use LouisInnovations\Sadad\Exceptions\SadadException;
use LouisInnovations\Sadad\Http\HttpClientInterface;
use LouisInnovations\Sadad\Refund\RefundManager;
use LouisInnovations\Sadad\SadadConfig;
use LouisInnovations\Sadad\Transaction\TransactionManager;
use PHPUnit\Framework\TestCase;

class RefundManagerTest extends TestCase
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

    /**
     * Build a stubbed Authenticator that returns the given token without HTTP calls.
     */
    private function makeAuthenticator(string $token = 'auth_token'): Authenticator
    {
        $authHttp = new class($token) implements HttpClientInterface {
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

        return new Authenticator($this->config, $authHttp);
    }

    /**
     * Build a TransactionManager that returns a fixed transaction array.
     *
     * @param array<string,mixed>|null $transaction NULL means the lookup fails.
     */
    private function makeTransactionManager(?array $transaction): TransactionManager
    {
        $txnResponse = $transaction !== null
            ? ['success' => true, 'transaction' => $transaction]
            : ['success' => false, 'error' => 'Not found'];

        $txnHttp = new class($txnResponse) implements HttpClientInterface {
            public function __construct(private array $response) {}

            public function post(string $url, array $data = [], array $headers = []): array
            {
                return [];
            }

            public function get(string $url, array $params = [], array $headers = []): array
            {
                return $this->response['transaction'] ?? [];
            }
        };

        // We inject a special stub TransactionManager directly via anonymous class.
        return new class($txnResponse) extends TransactionManager {
            public function __construct(private array $txnResponse)
            {
                // Intentionally skip parent constructor.
            }

            public function getTransaction(string $transactionNumber): array
            {
                return $this->txnResponse;
            }
        };
    }

    /**
     * Build a HttpClient stub used by RefundManager for the actual refund POST.
     */
    private function makeRefundHttpClient(array $refundResponse, bool $shouldThrow = false): HttpClientInterface
    {
        return new class($refundResponse, $shouldThrow) implements HttpClientInterface {
            public string $lastPostUrl    = '';
            public array  $lastPostData   = [];
            public array  $lastPostHeaders = [];

            public function __construct(
                private array $refundResponse,
                private bool $shouldThrow,
            ) {}

            public function post(string $url, array $data = [], array $headers = []): array
            {
                $this->lastPostUrl     = $url;
                $this->lastPostData    = $data;
                $this->lastPostHeaders = $headers;

                if ($this->shouldThrow) {
                    throw new SadadException('Refund HTTP failed', 'HTTP_ERROR');
                }

                return $this->refundResponse;
            }

            public function get(string $url, array $params = [], array $headers = []): array
            {
                return [];
            }
        };
    }

    private function validTransaction(array $overrides = []): array
    {
        return array_merge([
            'transactionno' => 'TXN-001',
            'status'        => 3,
            'amount'        => 100.00,
            'txnDate'       => date('Y-m-d H:i:s', time() - 86400), // yesterday
            'isRefunded'    => false,
        ], $overrides);
    }

    // -----------------------------------------------------------------------
    // refund() - success path
    // -----------------------------------------------------------------------

    public function testRefundSucceedsForEligibleTransaction(): void
    {
        $http    = $this->makeRefundHttpClient(['refundId' => 'REF-001', 'status' => 'refunded']);
        $manager = new RefundManager(
            $this->config,
            $http,
            $this->makeAuthenticator(),
            $this->makeTransactionManager($this->validTransaction())
        );

        $result = $manager->refund('TXN-001');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('refund_details', $result);
    }

    public function testRefundPostsToCorrectEndpoint(): void
    {
        $http    = $this->makeRefundHttpClient(['refundId' => 'REF-001']);
        $manager = new RefundManager(
            $this->config,
            $http,
            $this->makeAuthenticator(),
            $this->makeTransactionManager($this->validTransaction())
        );

        $manager->refund('TXN-001');

        $this->assertSame(
            'https://api-s.sadad.qa/api/transactions/refundTransaction',
            $http->lastPostUrl
        );
    }

    public function testRefundSendsTransactionNumber(): void
    {
        $http    = $this->makeRefundHttpClient(['refundId' => 'REF-001']);
        $manager = new RefundManager(
            $this->config,
            $http,
            $this->makeAuthenticator(),
            $this->makeTransactionManager($this->validTransaction(['transactionno' => 'TXN-999']))
        );

        $manager->refund('TXN-999');

        $this->assertSame('TXN-999', $http->lastPostData['transactionnumber']);
    }

    public function testRefundPassesAuthorizationHeader(): void
    {
        $http    = $this->makeRefundHttpClient(['refundId' => 'REF-001']);
        $manager = new RefundManager(
            $this->config,
            $http,
            $this->makeAuthenticator('secret_token'),
            $this->makeTransactionManager($this->validTransaction())
        );

        $manager->refund('TXN-001');

        $this->assertSame('Bearer secret_token', $http->lastPostHeaders['Authorization']);
    }

    // -----------------------------------------------------------------------
    // refund() - transaction not found
    // -----------------------------------------------------------------------

    public function testRefundThrowsWhenTransactionNotFound(): void
    {
        $http    = $this->makeRefundHttpClient([]);
        $manager = new RefundManager(
            $this->config,
            $http,
            $this->makeAuthenticator(),
            $this->makeTransactionManager(null)
        );

        $this->expectException(RefundException::class);
        $this->expectExceptionMessage('Transaction not found');

        $manager->refund('TXN-MISSING');
    }

    // -----------------------------------------------------------------------
    // refund() - non-success status
    // -----------------------------------------------------------------------

    public function testRefundThrowsForNonSuccessStatus(): void
    {
        $txn     = $this->validTransaction(['status' => 1]); // 1 = pending, not Success
        $http    = $this->makeRefundHttpClient([]);
        $manager = new RefundManager(
            $this->config,
            $http,
            $this->makeAuthenticator(),
            $this->makeTransactionManager($txn)
        );

        $this->expectException(RefundException::class);

        try {
            $manager->refund('TXN-001');
        } catch (RefundException $e) {
            $this->assertSame('REFUND_INVALID_STATUS', $e->getErrorCode());
            throw $e;
        }
    }

    // -----------------------------------------------------------------------
    // refund() - older than 3 months
    // -----------------------------------------------------------------------

    public function testRefundThrowsForExpiredTransaction(): void
    {
        $oldDate = date('Y-m-d H:i:s', strtotime('-4 months'));
        $txn     = $this->validTransaction(['txnDate' => $oldDate]);
        $http    = $this->makeRefundHttpClient([]);
        $manager = new RefundManager(
            $this->config,
            $http,
            $this->makeAuthenticator(),
            $this->makeTransactionManager($txn)
        );

        $this->expectException(RefundException::class);

        try {
            $manager->refund('TXN-001');
        } catch (RefundException $e) {
            $this->assertSame('REFUND_EXPIRED', $e->getErrorCode());
            throw $e;
        }
    }

    public function testRefundSucceedsForRecentTransaction(): void
    {
        $recentDate = date('Y-m-d H:i:s', strtotime('-1 month'));
        $txn        = $this->validTransaction(['txnDate' => $recentDate]);
        $http       = $this->makeRefundHttpClient(['refundId' => 'REF-002']);
        $manager    = new RefundManager(
            $this->config,
            $http,
            $this->makeAuthenticator(),
            $this->makeTransactionManager($txn)
        );

        $result = $manager->refund('TXN-001');

        $this->assertTrue($result['success']);
    }

    // -----------------------------------------------------------------------
    // refund() - already refunded
    // -----------------------------------------------------------------------

    public function testRefundThrowsForAlreadyRefundedTransaction(): void
    {
        $txn     = $this->validTransaction(['isRefunded' => true]);
        $http    = $this->makeRefundHttpClient([]);
        $manager = new RefundManager(
            $this->config,
            $http,
            $this->makeAuthenticator(),
            $this->makeTransactionManager($txn)
        );

        $this->expectException(RefundException::class);

        try {
            $manager->refund('TXN-001');
        } catch (RefundException $e) {
            $this->assertSame('REFUND_ALREADY_DONE', $e->getErrorCode());
            throw $e;
        }
    }

    // -----------------------------------------------------------------------
    // refund() - HTTP failure returns error array
    // -----------------------------------------------------------------------

    public function testRefundReturnsErrorArrayOnHttpFailure(): void
    {
        $http    = $this->makeRefundHttpClient([], true);
        $manager = new RefundManager(
            $this->config,
            $http,
            $this->makeAuthenticator(),
            $this->makeTransactionManager($this->validTransaction())
        );

        $result = $manager->refund('TXN-001');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Refund HTTP failed', $result['error']);
    }
}
