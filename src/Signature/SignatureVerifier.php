<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Signature;

use LouisInnovations\Sadad\Encryption\AESEncryptor;
use LouisInnovations\Sadad\Exceptions\SignatureException;

class SignatureVerifier
{
    /**
     * Verify a SADAD v1 callback by comparing the received checksumhash
     * against an expected hash generated from the remaining parameters.
     *
     * @param  array<string, mixed> $params    Full callback parameters including checksumhash.
     * @param  string               $secretKey Merchant secret key.
     * @return true                            Always returns true on success.
     * @throws SignatureException              When the signature does not match.
     */
    public static function verifyV1Callback(array $params, string $secretKey): bool
    {
        $received = $params['checksumhash'] ?? '';
        unset($params['checksumhash']);

        $expected = SignatureV1::generate($params, $secretKey);

        if (!hash_equals($expected, (string) $received)) {
            throw new SignatureException($expected, (string) $received);
        }

        return true;
    }

    /**
     * Verify a SADAD webhook payload using the v1 signature algorithm.
     * Functionally identical to verifyV1Callback — provided as a named alias
     * for webhook use-cases for clarity.
     *
     * @param  array<string, mixed> $payload   Full webhook payload including checksumhash.
     * @param  string               $secretKey Merchant secret key.
     * @return true                            Always returns true on success.
     * @throws SignatureException              When the signature does not match.
     */
    public static function verifyWebhook(array $payload, string $secretKey): bool
    {
        $received = $payload['checksumhash'] ?? '';
        unset($payload['checksumhash']);

        $expected = SignatureV1::generate($payload, $secretKey);

        if (!hash_equals($expected, (string) $received)) {
            throw new SignatureException($expected, (string) $received);
        }

        return true;
    }

    /**
     * Verify a SADAD v2 callback checksum.
     *
     * SADAD v2 verification protocol uses urlencode($secretKey) in both
     * the JSON data object and the AES decryption key. This differs from
     * generation (which uses the raw key) and is per the SADAD spec.
     *
     * Algorithm:
     *   1. Extract and remove checksumhash from params.
     *   2. Build verification data: ['postData' => $params, 'secretKey' => urlencode($secretKey)]
     *   3. Decrypt checksumhash using key: urlencode($secretKey) . $merchantId
     *   4. Extract salt (last 4 chars) and hash (first 64 chars) from decrypted string.
     *   5. Re-derive: sha256(json_encode(verificationData) . '|' . salt)
     *   6. Compare. Throw SignatureException on mismatch.
     *
     * @param  array<string, mixed> $params     Full callback parameters including checksumhash.
     * @param  string               $secretKey  Merchant secret key (raw, without encoding).
     * @param  string               $merchantId Merchant ID.
     * @return true                             Always returns true on success.
     * @throws SignatureException               When the checksum does not match.
     */
    public static function verifyV2Callback(array $params, string $secretKey, string $merchantId): bool
    {
        $receivedChecksum = $params['checksumhash'] ?? '';
        unset($params['checksumhash']);

        $encodedKey = urlencode($secretKey);

        $verificationData = [
            'postData'  => $params,
            'secretKey' => $encodedKey,
        ];

        $decryptionKey = $encodedKey . $merchantId;

        try {
            $decrypted = AESEncryptor::decrypt((string) $receivedChecksum, $decryptionKey);
        } catch (\RuntimeException $e) {
            throw new SignatureException('', (string) $receivedChecksum, 'Checksum decryption failed: ' . $e->getMessage());
        }

        $hash = substr($decrypted, 0, 64);
        $salt = substr($decrypted, 64, 4);

        $jsonString   = json_encode($verificationData);
        $expectedHash = hash('sha256', $jsonString . '|' . $salt);

        if (!hash_equals($expectedHash, $hash)) {
            throw new SignatureException($expectedHash, $hash);
        }

        return true;
    }
}
