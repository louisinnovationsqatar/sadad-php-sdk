<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Tests\Unit\Signature;

use LouisInnovations\Sadad\Encryption\AESEncryptor;
use LouisInnovations\Sadad\Signature\SignatureV2;
use PHPUnit\Framework\TestCase;

class SignatureV2Test extends TestCase
{
    private string $secretKey  = 'T1ds45#sGQbodf5';
    private string $merchantId = '7015085';

    private array $postData = [
        'CALLBACK_URL' => 'https://www.example.com/callback',
        'EMAIL'        => 'example@gmail.com',
        'MOBILE_NO'    => '77778888',
        'ORDER_ID'     => '1002',
        'TXN_AMOUNT'   => '200.00',
        'WEBSITE'      => 'www.example.com',
        'merchant_id'  => '1234567',
        'txnDate'      => '2022-01-15 20:12:40',
    ];

    public function testReturnsNonEmptyString(): void
    {
        $result = SignatureV2::generate($this->postData, $this->secretKey, $this->merchantId);

        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    public function testReturnsValidBase64(): void
    {
        $result = SignatureV2::generate($this->postData, $this->secretKey, $this->merchantId);

        // base64 characters only: A-Z a-z 0-9 + / =
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/]+=*$/', $result);

        // Verify it decodes without error
        $decoded = base64_decode($result, true);
        $this->assertNotFalse($decoded);
    }

    public function testChecksumCanBeVerifiedViaDecrypt(): void
    {
        $result = SignatureV2::generate($this->postData, $this->secretKey, $this->merchantId);

        // Decrypt using same key strategy: secretKey + merchantId
        $key       = $this->secretKey . $this->merchantId;
        $decrypted = AESEncryptor::decrypt($result, $key);

        // Decrypted string must be 68 chars: 64-char hex hash + 4-char salt
        $this->assertSame(68, strlen($decrypted));

        $hash = substr($decrypted, 0, 64);
        $salt = substr($decrypted, 64, 4);

        // Verify the hash is a valid 64-char lowercase hex string
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);

        // Re-derive the hash: json(checksumData) + '|' + salt → sha256
        $checksumData = [
            'postData'  => $this->postData,
            'secretKey' => $this->secretKey,
        ];

        $jsonString    = json_encode($checksumData);
        $expectedHash  = hash('sha256', $jsonString . '|' . $salt);

        $this->assertSame($expectedHash, $hash);
    }

    public function testDifferentPostDataProducesDifferentChecksum(): void
    {
        $postData2 = array_merge($this->postData, ['ORDER_ID' => '9999']);

        $checksum1 = SignatureV2::generate($this->postData, $this->secretKey, $this->merchantId);
        $checksum2 = SignatureV2::generate($postData2, $this->secretKey, $this->merchantId);

        // Different inputs must produce different encrypted blobs
        // (same encryption key, but different plaintext after different salts/hashes)
        // We verify by decrypting both and checking they differ
        $key = $this->secretKey . $this->merchantId;

        $decrypted1 = AESEncryptor::decrypt($checksum1, $key);
        $decrypted2 = AESEncryptor::decrypt($checksum2, $key);

        // The hash portions must differ (even if salts happened to collide)
        $hash1 = substr($decrypted1, 0, 64);
        $hash2 = substr($decrypted2, 0, 64);

        $this->assertNotSame($hash1, $hash2);
    }

    public function testDifferentSecretKeysProduceDifferentChecksums(): void
    {
        $checksum1 = SignatureV2::generate($this->postData, 'keyA123456789012', $this->merchantId);
        $checksum2 = SignatureV2::generate($this->postData, 'keyB123456789012', $this->merchantId);

        $this->assertNotSame($checksum1, $checksum2);
    }

    public function testDifferentMerchantIdsProduceDifferentChecksums(): void
    {
        $checksum1 = SignatureV2::generate($this->postData, $this->secretKey, '1000001');
        $checksum2 = SignatureV2::generate($this->postData, $this->secretKey, '2000002');

        $this->assertNotSame($checksum1, $checksum2);
    }
}
