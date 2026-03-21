<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Tests\Unit\Signature;

use LouisInnovations\Sadad\Encryption\AESEncryptor;
use LouisInnovations\Sadad\Encryption\SaltGenerator;
use LouisInnovations\Sadad\Exceptions\SignatureException;
use LouisInnovations\Sadad\Signature\SignatureV1;
use LouisInnovations\Sadad\Signature\SignatureVerifier;
use PHPUnit\Framework\TestCase;

class SignatureVerifierTest extends TestCase
{
    private string $secretKey  = 'T1ds45#sGQbodf5';
    private string $merchantId = '7015085';

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Build a valid V1 checksumhash for the given params and append it.
     */
    private function buildV1Params(array $params, string $secretKey): array
    {
        $hash                  = SignatureV1::generate($params, $secretKey);
        $params['checksumhash'] = $hash;
        return $params;
    }

    /**
     * Build a valid V2 checksumhash for verification.
     *
     * Per SADAD verification protocol, V2 uses urlencode($secretKey) in both
     * the data object and the encryption key.
     */
    private function buildV2Params(array $params, string $secretKey, string $merchantId): array
    {
        $encodedKey = urlencode($secretKey);

        $checksumData = [
            'postData'  => $params,
            'secretKey' => $encodedKey,
        ];

        $jsonString  = json_encode($checksumData);
        $salt        = SaltGenerator::generate(4);
        $finalString = $jsonString . '|' . $salt;
        $hash        = hash('sha256', $finalString);
        $hashString  = $hash . $salt;

        $key                   = $encodedKey . $merchantId;
        $params['checksumhash'] = AESEncryptor::encrypt($hashString, $key);
        return $params;
    }

    // -----------------------------------------------------------------------
    // verifyV1Callback
    // -----------------------------------------------------------------------

    public function testVerifyV1CallbackWithValidSignatureReturnsTrue(): void
    {
        $params = $this->buildV1Params([
            'ORDER_ID'   => '1001',
            'TXN_AMOUNT' => '200.00',
            'WEBSITE'    => 'www.example.com',
        ], $this->secretKey);

        $this->assertTrue(SignatureVerifier::verifyV1Callback($params, $this->secretKey));
    }

    public function testVerifyV1CallbackWithTamperedDataThrowsSignatureException(): void
    {
        $params = $this->buildV1Params([
            'ORDER_ID'   => '1001',
            'TXN_AMOUNT' => '200.00',
        ], $this->secretKey);

        // Tamper with the amount after signing
        $params['TXN_AMOUNT'] = '999.99';

        $this->expectException(SignatureException::class);
        SignatureVerifier::verifyV1Callback($params, $this->secretKey);
    }

    public function testVerifyV1CallbackWithTamperedChecksumThrowsSignatureException(): void
    {
        $params = $this->buildV1Params([
            'ORDER_ID' => '1001',
        ], $this->secretKey);

        // Replace the checksum with garbage
        $params['checksumhash'] = str_repeat('a', 64);

        $this->expectException(SignatureException::class);
        SignatureVerifier::verifyV1Callback($params, $this->secretKey);
    }

    public function testVerifyV1CallbackDoesNotMutateInputArray(): void
    {
        $params = $this->buildV1Params(['ORDER_ID' => '1001'], $this->secretKey);
        $copy   = $params;

        SignatureVerifier::verifyV1Callback($params, $this->secretKey);

        $this->assertSame($copy, $params);
    }

    // -----------------------------------------------------------------------
    // verifyWebhook
    // -----------------------------------------------------------------------

    public function testVerifyWebhookWithValidSignatureReturnsTrue(): void
    {
        $params = $this->buildV1Params([
            'ORDER_ID'      => '5000',
            'TXN_AMOUNT'    => '500.00',
            'TXNID'         => 'TXN123456',
            'STATUS'        => 'TXN_SUCCESS',
        ], $this->secretKey);

        $this->assertTrue(SignatureVerifier::verifyWebhook($params, $this->secretKey));
    }

    public function testVerifyWebhookWithTamperedPayloadThrowsSignatureException(): void
    {
        $params = $this->buildV1Params([
            'ORDER_ID'   => '5000',
            'TXN_AMOUNT' => '500.00',
            'STATUS'     => 'TXN_SUCCESS',
        ], $this->secretKey);

        // Tamper with status
        $params['STATUS'] = 'TXN_FAILURE';

        $this->expectException(SignatureException::class);
        SignatureVerifier::verifyWebhook($params, $this->secretKey);
    }

    public function testVerifyWebhookDoesNotMutateInputArray(): void
    {
        $params = $this->buildV1Params(['ORDER_ID' => '5000', 'STATUS' => 'TXN_SUCCESS'], $this->secretKey);
        $copy   = $params;

        SignatureVerifier::verifyWebhook($params, $this->secretKey);

        $this->assertSame($copy, $params);
    }

    // -----------------------------------------------------------------------
    // verifyV2Callback
    // -----------------------------------------------------------------------

    public function testVerifyV2CallbackWithValidChecksumReturnsTrue(): void
    {
        $params = $this->buildV2Params([
            'ORDER_ID'   => '2001',
            'TXN_AMOUNT' => '300.00',
            'WEBSITE'    => 'www.example.com',
        ], $this->secretKey, $this->merchantId);

        $this->assertTrue(SignatureVerifier::verifyV2Callback($params, $this->secretKey, $this->merchantId));
    }

    public function testVerifyV2CallbackWithSpecialCharKeyReturnsTrue(): void
    {
        $specialKey = 'T1ds45#sGQbodf5';  // contains # special character

        $params = $this->buildV2Params([
            'ORDER_ID'   => '3001',
            'TXN_AMOUNT' => '150.00',
        ], $specialKey, $this->merchantId);

        $this->assertTrue(SignatureVerifier::verifyV2Callback($params, $specialKey, $this->merchantId));
    }

    public function testVerifyV2CallbackWithTamperedDataThrowsSignatureException(): void
    {
        $params = $this->buildV2Params([
            'ORDER_ID'   => '2001',
            'TXN_AMOUNT' => '300.00',
        ], $this->secretKey, $this->merchantId);

        // Tamper with order ID after signing
        $params['ORDER_ID'] = '9999';

        $this->expectException(SignatureException::class);
        SignatureVerifier::verifyV2Callback($params, $this->secretKey, $this->merchantId);
    }

    public function testVerifyV2CallbackWithTamperedChecksumThrowsSignatureException(): void
    {
        $params = $this->buildV2Params([
            'ORDER_ID' => '2001',
        ], $this->secretKey, $this->merchantId);

        // Replace checksumhash with invalid base64-encoded garbage
        $params['checksumhash'] = base64_encode(str_repeat('X', 32));

        $this->expectException(SignatureException::class);
        SignatureVerifier::verifyV2Callback($params, $this->secretKey, $this->merchantId);
    }

    public function testVerifyV2CallbackDoesNotMutateInputArray(): void
    {
        $params = $this->buildV2Params(['ORDER_ID' => '2001'], $this->secretKey, $this->merchantId);
        $copy   = $params;

        SignatureVerifier::verifyV2Callback($params, $this->secretKey, $this->merchantId);

        $this->assertSame($copy, $params);
    }
}
