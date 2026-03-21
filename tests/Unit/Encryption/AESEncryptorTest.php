<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Tests\Unit\Encryption;

use LouisInnovations\Sadad\Encryption\AESEncryptor;
use PHPUnit\Framework\TestCase;

class AESEncryptorTest extends TestCase
{
    private const TEST_KEY  = 'T1ds45#sGQbodf5XYZ';  // > 16 chars to test truncation
    private const TEST_INPUT = 'Hello SADAD Payment';

    // --- Encrypt / Decrypt roundtrip ---

    public function testEncryptDecryptRoundtrip(): void
    {
        $encrypted = AESEncryptor::encrypt(self::TEST_INPUT, self::TEST_KEY);
        $decrypted  = AESEncryptor::decrypt($encrypted, self::TEST_KEY);

        $this->assertSame(self::TEST_INPUT, $decrypted);
    }

    public function testRoundtripWithShortKey(): void
    {
        $key        = 'shortkey';
        $input      = 'test data';
        $encrypted  = AESEncryptor::encrypt($input, $key);
        $decrypted  = AESEncryptor::decrypt($encrypted, $key);

        $this->assertSame($input, $decrypted);
    }

    public function testRoundtripWithExactly16ByteKey(): void
    {
        $key        = '1234567890123456';
        $input      = 'exact key length';
        $encrypted  = AESEncryptor::encrypt($input, $key);
        $decrypted  = AESEncryptor::decrypt($encrypted, $key);

        $this->assertSame($input, $decrypted);
    }

    // --- Base64 output ---

    public function testEncryptReturnsValidBase64(): void
    {
        $encrypted = AESEncryptor::encrypt(self::TEST_INPUT, self::TEST_KEY);

        $decoded = base64_decode($encrypted, strict: true);
        $this->assertNotFalse($decoded, 'Encrypted output is not valid base64.');
    }

    // --- Determinism with fixed IV ---

    public function testSameInputProducesSameOutput(): void
    {
        $first  = AESEncryptor::encrypt(self::TEST_INPUT, self::TEST_KEY);
        $second = AESEncryptor::encrypt(self::TEST_INPUT, self::TEST_KEY);

        $this->assertSame($first, $second, 'Fixed IV means same input must always produce same ciphertext.');
    }

    public function testDifferentKeysProduceDifferentOutput(): void
    {
        $enc1 = AESEncryptor::encrypt(self::TEST_INPUT, 'key_one_1234567x');
        $enc2 = AESEncryptor::encrypt(self::TEST_INPUT, 'key_two_abcdefgx');

        $this->assertNotSame($enc1, $enc2);
    }

    // --- Key truncation ---

    public function testKeyTruncatedToFirst16Bytes(): void
    {
        // Both keys share the same first 16 bytes → same ciphertext
        $keyFull     = '1234567890123456abcdef';
        $keyTruncated = '1234567890123456';

        $enc1 = AESEncryptor::encrypt(self::TEST_INPUT, $keyFull);
        $enc2 = AESEncryptor::encrypt(self::TEST_INPUT, $keyTruncated);

        $this->assertSame($enc1, $enc2);
    }

    // --- Empty / edge-case inputs ---

    public function testEncryptDecryptEmptyString(): void
    {
        $encrypted = AESEncryptor::encrypt('', self::TEST_KEY);
        $decrypted  = AESEncryptor::decrypt($encrypted, self::TEST_KEY);

        $this->assertSame('', $decrypted);
    }

    public function testEncryptReturnsNonEmptyString(): void
    {
        $encrypted = AESEncryptor::encrypt(self::TEST_INPUT, self::TEST_KEY);
        $this->assertNotEmpty($encrypted);
    }
}
