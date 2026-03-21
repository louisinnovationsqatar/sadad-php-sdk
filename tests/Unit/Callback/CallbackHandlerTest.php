<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Tests\Unit\Callback;

use InvalidArgumentException;
use LouisInnovations\Sadad\Callback\CallbackHandler;
use LouisInnovations\Sadad\Callback\CallbackResult;
use LouisInnovations\Sadad\Encryption\AESEncryptor;
use LouisInnovations\Sadad\Encryption\SaltGenerator;
use LouisInnovations\Sadad\Exceptions\SignatureException;
use LouisInnovations\Sadad\SadadConfig;
use LouisInnovations\Sadad\Signature\SignatureV1;
use PHPUnit\Framework\TestCase;

class CallbackHandlerTest extends TestCase
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

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Build a v1 callback payload with a valid SHA-256 checksumhash.
     */
    private function buildV1Payload(array $data): array
    {
        $hash                  = SignatureV1::generate($data, $this->config->secretKey);
        $data['checksumhash']  = $hash;
        return $data;
    }

    /**
     * Build a v2 callback payload with a valid AES checksumhash.
     *
     * Per SignatureVerifier::verifyV2Callback, verification uses urlencode($secretKey)
     * in both the data object and the encryption key.
     */
    private function buildV2Payload(array $data): array
    {
        $encodedKey = urlencode($this->config->secretKey);

        $checksumData = [
            'postData'  => $data,
            'secretKey' => $encodedKey,
        ];

        $jsonString  = json_encode($checksumData);
        $salt        = SaltGenerator::generate(4);
        $finalString = $jsonString . '|' . $salt;
        $hash        = hash('sha256', $finalString);
        $hashString  = $hash . $salt;

        $key                  = $encodedKey . $this->config->merchantId;
        $data['checksumhash'] = AESEncryptor::encrypt($hashString, $key);
        return $data;
    }

    private function defaultCallbackData(): array
    {
        return [
            'ORDERID'            => 'ORD-CB-001',
            'transaction_number' => 'TXN-7654321',
            'TXNAMOUNT'          => '200.50',
            'RESPCODE'           => '1',
            'RESPMSG'            => 'Transaction successful',
            'STATUS'             => 'TXN_SUCCESS',
        ];
    }

    // -----------------------------------------------------------------------
    // V1 callback — valid signature
    // -----------------------------------------------------------------------

    public function testV1CallbackWithValidSignatureReturnsCallbackResult(): void
    {
        $payload = $this->buildV1Payload($this->defaultCallbackData());
        $handler = new CallbackHandler($this->config);
        $result  = $handler->handle($payload, 'v1.1');

        $this->assertInstanceOf(CallbackResult::class, $result);
    }

    public function testV1CallbackParsesAllFields(): void
    {
        $payload = $this->buildV1Payload($this->defaultCallbackData());
        $handler = new CallbackHandler($this->config);
        $result  = $handler->handle($payload, 'v1.1');

        $this->assertSame('ORD-CB-001', $result->orderNumber);
        $this->assertSame('TXN-7654321', $result->transactionNumber);
        $this->assertSame(200.50, $result->amount);
        $this->assertSame('1', $result->responseCode);
        $this->assertSame('Transaction successful', $result->responseMessage);
        $this->assertSame('TXN_SUCCESS', $result->status);
    }

    // -----------------------------------------------------------------------
    // isSuccess logic
    // -----------------------------------------------------------------------

    public function testIsSuccessTrueWhenRespcode1AsString(): void
    {
        $data    = array_merge($this->defaultCallbackData(), ['RESPCODE' => '1']);
        $payload = $this->buildV1Payload($data);
        $handler = new CallbackHandler($this->config);

        $this->assertTrue($handler->handle($payload, 'v1.1')->isSuccess);
    }

    public function testIsSuccessTrueWhenRespcode1AsInteger(): void
    {
        $data    = array_merge($this->defaultCallbackData(), ['RESPCODE' => 1]);
        $payload = $this->buildV1Payload($data);
        $handler = new CallbackHandler($this->config);

        $this->assertTrue($handler->handle($payload, 'v1.1')->isSuccess);
    }

    public function testIsSuccessFalseForNonOneRespcode(): void
    {
        foreach (['0', '2', 'E101', 'TXN_FAILURE'] as $code) {
            $data    = array_merge($this->defaultCallbackData(), ['RESPCODE' => $code]);
            $payload = $this->buildV1Payload($data);
            $handler = new CallbackHandler($this->config);

            $this->assertFalse($handler->handle($payload, 'v1.1')->isSuccess, "Expected isSuccess=false for RESPCODE=$code");
        }
    }

    // -----------------------------------------------------------------------
    // V1 callback — tampered data
    // -----------------------------------------------------------------------

    public function testV1CallbackWithTamperedDataThrowsSignatureException(): void
    {
        $payload = $this->buildV1Payload($this->defaultCallbackData());

        // Tamper with amount after signing
        $payload['TXNAMOUNT'] = '9999.99';

        $this->expectException(SignatureException::class);
        (new CallbackHandler($this->config))->handle($payload, 'v1.1');
    }

    public function testV1CallbackWithTamperedChecksumThrowsSignatureException(): void
    {
        $payload = $this->buildV1Payload($this->defaultCallbackData());
        $payload['checksumhash'] = str_repeat('x', 64);

        $this->expectException(SignatureException::class);
        (new CallbackHandler($this->config))->handle($payload, 'v1.1');
    }

    // -----------------------------------------------------------------------
    // V2 callback (v2.1)
    // -----------------------------------------------------------------------

    public function testV21CallbackWithValidChecksumReturnsCallbackResult(): void
    {
        $payload = $this->buildV2Payload($this->defaultCallbackData());
        $handler = new CallbackHandler($this->config);
        $result  = $handler->handle($payload, 'v2.1');

        $this->assertInstanceOf(CallbackResult::class, $result);
        $this->assertTrue($result->isSuccess);
    }

    public function testV21CallbackParsesAllFields(): void
    {
        $payload = $this->buildV2Payload($this->defaultCallbackData());
        $handler = new CallbackHandler($this->config);
        $result  = $handler->handle($payload, 'v2.1');

        $this->assertSame('ORD-CB-001', $result->orderNumber);
        $this->assertSame('TXN-7654321', $result->transactionNumber);
        $this->assertSame(200.50, $result->amount);
        $this->assertSame('1', $result->responseCode);
        $this->assertSame('Transaction successful', $result->responseMessage);
        $this->assertSame('TXN_SUCCESS', $result->status);
    }

    public function testV21CallbackWithTamperedDataThrowsSignatureException(): void
    {
        $payload = $this->buildV2Payload($this->defaultCallbackData());
        $payload['TXNAMOUNT'] = '9999.99';

        $this->expectException(SignatureException::class);
        (new CallbackHandler($this->config))->handle($payload, 'v2.1');
    }

    // -----------------------------------------------------------------------
    // V2 callback (v2.2) — same algorithm as v2.1
    // -----------------------------------------------------------------------

    public function testV22CallbackWithValidChecksumReturnsCallbackResult(): void
    {
        $payload = $this->buildV2Payload($this->defaultCallbackData());
        $handler = new CallbackHandler($this->config);
        $result  = $handler->handle($payload, 'v2.2');

        $this->assertInstanceOf(CallbackResult::class, $result);
    }

    // -----------------------------------------------------------------------
    // Default version
    // -----------------------------------------------------------------------

    public function testDefaultVersionIsV11(): void
    {
        $payload = $this->buildV1Payload($this->defaultCallbackData());
        $handler = new CallbackHandler($this->config);

        // Should succeed with default version (v1.1)
        $result = $handler->handle($payload);
        $this->assertInstanceOf(CallbackResult::class, $result);
    }

    // -----------------------------------------------------------------------
    // Unsupported version
    // -----------------------------------------------------------------------

    public function testUnsupportedVersionThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new CallbackHandler($this->config))->handle([], 'v3.0');
    }
}
