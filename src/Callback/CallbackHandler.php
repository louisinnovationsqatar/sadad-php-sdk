<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Callback;

use InvalidArgumentException;
use LouisInnovations\Sadad\SadadConfig;
use LouisInnovations\Sadad\Signature\SignatureVerifier;

class CallbackHandler
{
    public function __construct(private readonly SadadConfig $config)
    {
    }

    /**
     * Process a SADAD payment callback (redirect back to merchant site).
     *
     * Supported versions:
     *   - 'v1.1'  — SHA-256 signature via SignatureVerifier::verifyV1Callback()
     *   - 'v2.1'  — AES-128-CBC checksumhash via SignatureVerifier::verifyV2Callback()
     *   - 'v2.2'  — Same as v2.1 (same verification algorithm)
     *
     * Field mapping (SADAD POST fields → CallbackResult properties):
     *   ORDERID            → orderNumber
     *   transaction_number → transactionNumber
     *   TXNAMOUNT          → amount
     *   RESPCODE           → responseCode
     *   RESPMSG            → responseMessage
     *   STATUS             → status
     *
     * isSuccess = RESPCODE === '1' || RESPCODE === 1
     *
     * @param  array<string, mixed> $postData Raw POST data from the SADAD callback.
     * @param  string               $version  Checkout version: 'v1.1', 'v2.1', or 'v2.2'.
     * @return CallbackResult
     * @throws \LouisInnovations\Sadad\Exceptions\SignatureException When signature verification fails.
     * @throws InvalidArgumentException                             When an unsupported version is passed.
     */
    public function handle(array $postData, string $version = 'v1.1'): CallbackResult
    {
        match ($version) {
            'v1.1'        => SignatureVerifier::verifyV1Callback($postData, $this->config->secretKey),
            'v2.1', 'v2.2' => SignatureVerifier::verifyV2Callback($postData, $this->config->secretKey, $this->config->merchantId),
            default       => throw new InvalidArgumentException(
                sprintf('Unsupported callback version "%s". Supported: v1.1, v2.1, v2.2.', $version)
            ),
        };

        $respCode  = $postData['RESPCODE'] ?? '';
        $isSuccess = $respCode === '1' || $respCode === 1;

        return new CallbackResult(
            isSuccess:         $isSuccess,
            orderNumber:       (string) ($postData['ORDERID'] ?? ''),
            transactionNumber: (string) ($postData['transaction_number'] ?? ''),
            amount:            (float)  ($postData['TXNAMOUNT'] ?? 0.0),
            responseCode:      (string) $respCode,
            responseMessage:   (string) ($postData['RESPMSG'] ?? ''),
            status:            (string) ($postData['STATUS'] ?? ''),
        );
    }
}
