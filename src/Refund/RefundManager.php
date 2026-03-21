<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Refund;

use LouisInnovations\Sadad\Auth\Authenticator;
use LouisInnovations\Sadad\Exceptions\RefundException;
use LouisInnovations\Sadad\Http\HttpClientInterface;
use LouisInnovations\Sadad\SadadConfig;
use LouisInnovations\Sadad\Transaction\TransactionManager;

class RefundManager
{
    private const STATUS_SUCCESS  = 3;
    private const STATUS_REFUNDED = 4;

    /** Maximum age of a transaction in seconds before refund is disallowed (3 months ≈ 90 days). */
    private const MAX_REFUND_AGE_SECONDS = 90 * 24 * 3600;

    public function __construct(
        private SadadConfig $config,
        private HttpClientInterface $httpClient,
        private Authenticator $authenticator,
        private TransactionManager $transactionManager,
    ) {}

    /**
     * Issue a full refund for the given transaction.
     *
     * SADAD supports full refunds only; no amount parameter is accepted.
     *
     * @param  string               $transactionNumber The transaction to refund.
     * @return array<string, mixed>                    Response with 'success' and 'refund_details' or 'error'.
     *
     * @throws RefundException When the transaction cannot be refunded.
     */
    public function refund(string $transactionNumber): array
    {
        // 1. Fetch transaction details.
        $txnResult = $this->transactionManager->getTransaction($transactionNumber);

        if (empty($txnResult['success']) || empty($txnResult['transaction'])) {
            throw new RefundException(
                'Transaction not found: ' . $transactionNumber,
                'REFUND_NOT_FOUND'
            );
        }

        $transaction = $txnResult['transaction'];

        // 2a. Must be status 3 (Success).
        $status = (int) ($transaction['status'] ?? 0);
        if ($status !== self::STATUS_SUCCESS) {
            throw new RefundException(
                'Transaction status is not eligible for refund.',
                'REFUND_INVALID_STATUS'
            );
        }

        // 2b. Must be within 3 months.
        $txnDate = $transaction['txnDate'] ?? $transaction['createdAt'] ?? null;
        if ($txnDate !== null) {
            $txnTimestamp = is_int($txnDate) ? $txnDate : strtotime($txnDate);
            if ($txnTimestamp !== false && (time() - $txnTimestamp) > self::MAX_REFUND_AGE_SECONDS) {
                throw new RefundException(
                    'Transaction is older than 3 months and cannot be refunded.',
                    'REFUND_EXPIRED'
                );
            }
        }

        // 2c. Must not already be refunded.
        $alreadyRefunded = $transaction['isRefunded'] ?? $transaction['refunded'] ?? false;
        if ((bool) $alreadyRefunded) {
            throw new RefundException(
                'Transaction has already been refunded.',
                'REFUND_ALREADY_DONE'
            );
        }

        // 3. Post refund request.
        try {
            $token = $this->authenticator->getAccessToken();

            $response = $this->httpClient->post(
                $this->config->getApiBaseUrl() . '/transactions/refundTransaction',
                ['transactionnumber' => $transactionNumber],
                ['Authorization' => 'Bearer ' . $token]
            );

            return [
                'success'        => true,
                'refund_details' => $response,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }
}
