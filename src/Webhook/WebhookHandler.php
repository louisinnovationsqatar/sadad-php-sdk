<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Webhook;

use LouisInnovations\Sadad\SadadConfig;
use LouisInnovations\Sadad\Signature\SignatureVerifier;

class WebhookHandler
{
    public function __construct(private readonly SadadConfig $config)
    {
    }

    /**
     * Process an incoming SADAD webhook payload.
     *
     * 1. Verifies the SHA-256 checksumhash via SignatureVerifier::verifyWebhook().
     * 2. Parses the payload into a WebhookResult value object.
     * 3. isSuccess is true when transactionStatus == 3.
     *
     * @param  array<string, mixed> $payload Raw POST data from the SADAD webhook.
     * @return WebhookResult
     * @throws \LouisInnovations\Sadad\Exceptions\SignatureException When signature verification fails.
     */
    public function handle(array $payload): WebhookResult
    {
        SignatureVerifier::verifyWebhook($payload, $this->config->secretKey);

        $transactionStatus = (int) ($payload['transactionStatus'] ?? 0);
        $isSuccess         = $transactionStatus === 3;

        return new WebhookResult(
            isSuccess:         $isSuccess,
            message:           (string) ($payload['message'] ?? ''),
            transactionNumber: (string) ($payload['transaction_number'] ?? ''),
            orderNumber:       (string) ($payload['ORDER_ID'] ?? ''),
            amount:            (float)  ($payload['TXN_AMOUNT'] ?? 0.0),
            merchantId:        (string) ($payload['merchant_id'] ?? ''),
            isTestMode:        (bool)   ($payload['isTestMode'] ?? false),
            invoiceNumber:     isset($payload['invoiceNumber']) ? (string) $payload['invoiceNumber'] : null,
        );
    }

    /**
     * Return the standard success acknowledgement array.
     *
     * SADAD expects the merchant webhook endpoint to respond with this JSON
     * payload to confirm receipt.
     *
     * @return array{status: string}
     */
    public static function successResponse(): array
    {
        return ['status' => 'success'];
    }
}
