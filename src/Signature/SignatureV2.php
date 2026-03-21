<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Signature;

use LouisInnovations\Sadad\Encryption\AESEncryptor;
use LouisInnovations\Sadad\Encryption\SaltGenerator;

class SignatureV2
{
    /**
     * Generate an AES-128-CBC encrypted checksum for SADAD v2 checkout.
     *
     * Algorithm:
     *   1. Build data object: ['postData' => $postData, 'secretKey' => $secretKey]
     *   2. json_encode($data)
     *   3. Generate 4-char salt via SaltGenerator::generate(4)
     *   4. Concatenate: $jsonString . '|' . $salt
     *   5. hash('sha256', $concatenated) → 64-char hex string
     *   6. Append salt: $hash . $salt  (68 chars total)
     *   7. AES-128-CBC encrypt with key = $secretKey . $merchantId (truncated to 16 bytes)
     *   8. Return base64-encoded encrypted string
     *
     * @param  array<string, mixed> $postData   Checkout parameters.
     * @param  string               $secretKey  Merchant secret key.
     * @param  string               $merchantId Merchant ID.
     * @return string                           Base64-encoded AES encrypted checksum.
     */
    public static function generate(array $postData, string $secretKey, string $merchantId): string
    {
        $checksumData = [
            'postData'  => $postData,
            'secretKey' => $secretKey,
        ];

        $jsonString  = json_encode($checksumData);
        $salt        = SaltGenerator::generate(4);
        $finalString = $jsonString . '|' . $salt;
        $hash        = hash('sha256', $finalString);
        $hashString  = $hash . $salt;

        $key = $secretKey . $merchantId;
        return AESEncryptor::encrypt($hashString, $key);
    }
}
