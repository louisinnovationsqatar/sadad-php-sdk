<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Encryption;

use RuntimeException;

class AESEncryptor
{
    private const ALGORITHM = 'AES-128-CBC';
    private const IV        = '@@@@&&&&####$$$$';  // fixed 16-byte IV

    public static function encrypt(string $input, string $key): string
    {
        $truncatedKey = substr($key, 0, 16);

        $encrypted = openssl_encrypt(
            $input,
            self::ALGORITHM,
            $truncatedKey,
            OPENSSL_RAW_DATA,
            self::IV
        );

        if ($encrypted === false) {
            throw new RuntimeException('AES encryption failed: ' . openssl_error_string());
        }

        return base64_encode($encrypted);
    }

    public static function decrypt(string $input, string $key): string
    {
        $truncatedKey = substr($key, 0, 16);
        $rawData      = base64_decode($input, strict: false);

        $decrypted = openssl_decrypt(
            $rawData,
            self::ALGORITHM,
            $truncatedKey,
            OPENSSL_RAW_DATA,
            self::IV
        );

        if ($decrypted === false) {
            throw new RuntimeException('AES decryption failed: ' . openssl_error_string());
        }

        return $decrypted;
    }
}
